<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\TimelineEntryDTO;
use App\Enums\SystemEventSeverity;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\SystemEvent;
use App\Models\User;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Unified user audit timeline (explanatory only; not source of financial truth).
 *
 * Financial truth = wallet_transactions (ledger). system_events in this timeline are
 * always non-financial; financial system_events are never queried or shown to avoid
 * duplication with the ledger. Refund workflow is represented only by system_events
 * (refund.requested, refund.approved); wallet_transactions map strictly to type
 * wallet_transaction and do not carry workflow meaning.
 *
 * When a type filter is set, only the relevant source is queried (no system_event,
 * order, or fulfillment queries when type is wallet_transaction, and vice versa).
 * In-memory filtering is applied only when the selected source can produce multiple
 * types (e.g. system_event vs loyalty_tier_change, or refund_request by event_type).
 *
 * Do not change ledger logic. All queries are capped and index-supported; date
 * filters use startOfDay/endOfDay for index use.
 *
 * @phpstan-type FilterShape array{
 *     financial_only?: bool,
 *     severity?: string,
 *     date_from?: string|null,
 *     date_to?: string|null,
 *     type?: string
 * }
 */
class UserAuditTimelineService
{
    private const PER_SOURCE_LIMIT = 100;

    /**
     * Fetch only sources relevant to type filter, merge, sort by occurredAt desc, return top $limit.
     *
     * @param  FilterShape  $filters  Optional: financial_only, severity, date_from (Y-m-d), date_to (Y-m-d), type
     * @return Collection<int, TimelineEntryDTO>
     */
    public function buildForUser(User $user, int $limit = 100, array $filters = []): Collection
    {
        $type = $filters['type'] ?? '';
        $sources = $this->sourcesForTypeFilter($type);

        $entries = collect();
        if (in_array('system_events', $sources, true)) {
            $entries = $entries->merge($this->fetchSystemEvents($user, $filters));
        }
        if (in_array('wallet_transactions', $sources, true)) {
            $entries = $entries->merge($this->fetchWalletTransactions($user, $filters));
        }
        if (in_array('orders', $sources, true)) {
            $entries = $entries->merge($this->fetchOrders($user, $filters));
        }
        if (in_array('fulfillments', $sources, true)) {
            $entries = $entries->merge($this->fetchFulfillments($user, $filters));
        }

        $sorted = $entries
            ->sortByDesc(fn (TimelineEntryDTO $dto) => $dto->occurredAt->getTimestamp())
            ->values();

        $sorted = $this->applyTypeFilter($sorted, $type);

        return $sorted->take($limit);
    }

    /**
     * Which sources to query when type filter is set. Empty type = all sources.
     * Strict: wallet_transaction → only wallet_transactions; system_event / refund_request → only system_events.
     *
     * @return list<string>
     */
    private function sourcesForTypeFilter(string $type): array
    {
        if ($type === '') {
            return ['system_events', 'wallet_transactions', 'orders', 'fulfillments'];
        }
        if ($type === 'system_event' || $type === 'loyalty_tier_change' || $type === 'refund_request') {
            return ['system_events'];
        }
        if ($type === 'wallet_transaction') {
            return ['wallet_transactions'];
        }
        if ($type === 'order') {
            return ['orders'];
        }
        if ($type === 'fulfillment') {
            return ['fulfillments'];
        }

        return ['system_events', 'wallet_transactions', 'orders', 'fulfillments'];
    }

    /**
     * Apply in-memory type filter only when the queried source can produce multiple types.
     * Single-type sources (wallet_transaction, order, fulfillment) are not filtered.
     *
     * @param  \Illuminate\Support\Collection<int, TimelineEntryDTO>  $entries
     * @return \Illuminate\Support\Collection<int, TimelineEntryDTO>
     */
    private function applyTypeFilter(Collection $entries, string $type): Collection
    {
        if ($type === '') {
            return $entries;
        }
        if ($type === 'wallet_transaction' || $type === 'order' || $type === 'fulfillment') {
            return $entries;
        }
        if ($type === 'refund_request') {
            return $entries->filter(fn (TimelineEntryDTO $dto) => $dto->eventType !== null && in_array($dto->eventType, ['refund.requested', 'refund.approved'], true))->values();
        }

        return $entries->filter(fn (TimelineEntryDTO $dto) => $dto->type === $type)->values();
    }

    /**
     * Parse date string to start-of-day and end-of-day for index-safe range queries.
     *
     * @return array{0: \Carbon\Carbon|null, 1: \Carbon\Carbon|null}
     */
    private function dateRangeFromFilters(array $filters): array
    {
        $from = null;
        $to = null;
        if (! empty($filters['date_from'])) {
            $from = Carbon::parse($filters['date_from'])->startOfDay();
        }
        if (! empty($filters['date_to'])) {
            $to = Carbon::parse($filters['date_to'])->endOfDay();
        }

        return [$from, $to];
    }

    /**
     * Non-financial system_events only (financial truth is wallet_transactions).
     * User, Order, WalletTransaction, Fulfillment entities; index-safe date range.
     *
     * @param  FilterShape  $filters
     * @return Collection<int, TimelineEntryDTO>
     */
    private function fetchSystemEvents(User $user, array $filters): Collection
    {
        [$dateFrom, $dateTo] = $this->dateRangeFromFilters($filters);
        $events = collect();

        $base = SystemEvent::query()
            ->where('is_financial', false)
            ->where('entity_type', User::class)
            ->where('entity_id', $user->id);
        $events = $events->merge(
            $this->applySystemEventFilters($base, $filters, $dateFrom, $dateTo)->orderByDesc('created_at')->limit(self::PER_SOURCE_LIMIT)->get()
        );

        $orderIds = Order::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(self::PER_SOURCE_LIMIT)
            ->pluck('id')
            ->all();

        if ($orderIds !== []) {
            $base = SystemEvent::query()
                ->where('is_financial', false)
                ->where('entity_type', Order::class)
                ->whereIn('entity_id', $orderIds);
            $events = $events->merge(
                $this->applySystemEventFilters($base, $filters, $dateFrom, $dateTo)->orderByDesc('created_at')->limit(self::PER_SOURCE_LIMIT)->get()
            );
        }

        $wallet = $user->wallet;
        if ($wallet !== null) {
            $wtIds = WalletTransaction::query()
                ->where('wallet_id', $wallet->id)
                ->orderByDesc('created_at')
                ->limit(self::PER_SOURCE_LIMIT)
                ->pluck('id')
                ->all();

            if ($wtIds !== []) {
                $base = SystemEvent::query()
                    ->where('is_financial', false)
                    ->where('entity_type', WalletTransaction::class)
                    ->whereIn('entity_id', $wtIds);
                $events = $events->merge(
                    $this->applySystemEventFilters($base, $filters, $dateFrom, $dateTo)->orderByDesc('created_at')->limit(self::PER_SOURCE_LIMIT)->get()
                );
            }
        }

        $fulfillmentIds = Fulfillment::query()
            ->whereIn('order_id', Order::query()->where('user_id', $user->id)->limit(self::PER_SOURCE_LIMIT)->pluck('id'))
            ->orderByDesc('created_at')
            ->limit(self::PER_SOURCE_LIMIT)
            ->pluck('id')
            ->all();

        if ($fulfillmentIds !== []) {
            $base = SystemEvent::query()
                ->where('is_financial', false)
                ->where('entity_type', Fulfillment::class)
                ->whereIn('entity_id', $fulfillmentIds);
            $events = $events->merge(
                $this->applySystemEventFilters($base, $filters, $dateFrom, $dateTo)->orderByDesc('created_at')->limit(self::PER_SOURCE_LIMIT)->get()
            );
        }

        $events = $events->unique('id')->sortByDesc('created_at')->values()->take(self::PER_SOURCE_LIMIT);

        return $events->map(fn (SystemEvent $e) => $this->systemEventToDto($e));
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<SystemEvent>  $query
     * @param  FilterShape  $filters
     * @return \Illuminate\Database\Eloquent\Builder<SystemEvent>
     */
    private function applySystemEventFilters($query, array $filters, ?Carbon $dateFrom, ?Carbon $dateTo)
    {
        if (isset($filters['severity']) && $filters['severity'] !== '' && $filters['severity'] !== 'all') {
            $query->where('severity', $filters['severity']);
        }
        if ($dateFrom !== null) {
            $query->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo !== null) {
            $query->where('created_at', '<=', $dateTo);
        }

        return $query;
    }

    private function systemEventToDto(SystemEvent $e): TimelineEntryDTO
    {
        $type = $e->event_type === 'tier.upgraded' ? 'loyalty_tier_change' : 'system_event';
        $title = $e->event_type;
        $description = $this->describeSystemEvent($e);
        $severity = $e->severity ?? SystemEventSeverity::Info;
        $meta = is_array($e->meta) ? $e->meta : [];

        return new TimelineEntryDTO(
            type: $type,
            title: $title,
            description: $description,
            occurredAt: $e->created_at,
            severity: $severity,
            isFinancial: (bool) $e->is_financial,
            meta: array_merge($meta, ['entity_type' => $e->entity_type, 'entity_id' => $e->entity_id]),
            sourceKey: 'system_event:'.$e->id,
            eventType: $e->event_type,
        );
    }

    private function describeSystemEvent(SystemEvent $e): string
    {
        $parts = [];
        if ($e->entity_type) {
            $parts[] = class_basename($e->entity_type).' #'.$e->entity_id;
        }
        if ($e->meta && isset($e->meta['order_number'])) {
            $parts[] = (string) $e->meta['order_number'];
        }
        if ($e->meta && isset($e->meta['new_tier'])) {
            $parts[] = '→ '.$e->meta['new_tier'];
        }

        return $parts !== [] ? implode(' · ', $parts) : $e->event_type;
    }

    /**
     * Wallet transactions (financial truth). Index: wallet_id; index-safe date range.
     *
     * @param  FilterShape  $filters
     * @return Collection<int, TimelineEntryDTO>
     */
    private function fetchWalletTransactions(User $user, array $filters): Collection
    {
        $wallet = $user->wallet;
        if ($wallet === null) {
            return collect();
        }

        [$dateFrom, $dateTo] = $this->dateRangeFromFilters($filters);

        $query = WalletTransaction::query()
            ->where('wallet_id', $wallet->id);

        if (! empty($filters['financial_only'])) {
            $query->where('status', WalletTransaction::STATUS_POSTED);
        }
        if ($dateFrom !== null) {
            $query->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo !== null) {
            $query->where('created_at', '<=', $dateTo);
        }

        return $query
            ->orderByDesc('created_at')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get()
            ->map(fn (WalletTransaction $wt) => $this->walletTransactionToDto($wt));
    }

    private function walletTransactionToDto(WalletTransaction $wt): TimelineEntryDTO
    {
        $title = $wt->type->value.' '.$wt->direction->value;
        $description = $wt->amount.' '.($wt->meta['currency'] ?? 'USD').' · '.$wt->status;
        $meta = is_array($wt->meta) ? $wt->meta : [];
        $meta['wallet_transaction_id'] = $wt->id;
        $meta['type'] = $wt->type->value;
        $meta['direction'] = $wt->direction->value;
        $meta['amount'] = (string) $wt->amount;

        return new TimelineEntryDTO(
            type: 'wallet_transaction',
            title: $title,
            description: $description,
            occurredAt: $wt->created_at,
            severity: SystemEventSeverity::Info,
            isFinancial: $wt->status === WalletTransaction::STATUS_POSTED,
            meta: $meta,
            sourceKey: 'wallet_transaction:'.$wt->id,
        );
    }

    /**
     * Orders for user. Index: user_id, created_at; index-safe date range.
     *
     * @param  FilterShape  $filters
     * @return Collection<int, TimelineEntryDTO>
     */
    private function fetchOrders(User $user, array $filters): Collection
    {
        [$dateFrom, $dateTo] = $this->dateRangeFromFilters($filters);

        $query = Order::query()->where('user_id', $user->id);

        if (! empty($filters['financial_only'])) {
            $query->whereNotNull('paid_at');
        }
        if ($dateFrom !== null) {
            $query->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo !== null) {
            $query->where('created_at', '<=', $dateTo);
        }

        return $query
            ->orderByDesc('created_at')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get()
            ->map(fn (Order $order) => $this->orderToDto($order));
    }

    private function orderToDto(Order $order): TimelineEntryDTO
    {
        $meta = is_array($order->meta) ? $order->meta : [];
        $meta['order_id'] = $order->id;
        $meta['order_number'] = $order->order_number;
        $meta['total'] = (string) $order->total;
        $meta['status'] = $order->status->value;

        return new TimelineEntryDTO(
            type: 'order',
            title: 'Order '.$order->order_number,
            description: $order->total.' '.$order->currency.' · '.$order->status->value,
            occurredAt: $order->created_at,
            severity: SystemEventSeverity::Info,
            isFinancial: $order->paid_at !== null,
            meta: $meta,
            sourceKey: 'order:'.$order->id,
        );
    }

    /**
     * Fulfillments for user's orders. Index: order_id; index-safe date range.
     *
     * @param  FilterShape  $filters
     * @return Collection<int, TimelineEntryDTO>
     */
    private function fetchFulfillments(User $user, array $filters): Collection
    {
        $orderIds = Order::query()
            ->where('user_id', $user->id)
            ->limit(self::PER_SOURCE_LIMIT)
            ->pluck('id')
            ->all();

        if ($orderIds === []) {
            return collect();
        }

        [$dateFrom, $dateTo] = $this->dateRangeFromFilters($filters);

        $query = Fulfillment::query()->whereIn('order_id', $orderIds);

        if ($dateFrom !== null) {
            $query->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo !== null) {
            $query->where('created_at', '<=', $dateTo);
        }

        return $query
            ->orderByDesc('created_at')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get()
            ->map(fn (Fulfillment $f) => $this->fulfillmentToDto($f));
    }

    private function fulfillmentToDto(Fulfillment $f): TimelineEntryDTO
    {
        $meta = is_array($f->meta) ? $f->meta : [];
        $meta['fulfillment_id'] = $f->id;
        $meta['order_id'] = $f->order_id;
        $meta['status'] = $f->status->value;
        $meta['provider'] = $f->provider;

        return new TimelineEntryDTO(
            type: 'fulfillment',
            title: 'Fulfillment #'.$f->id,
            description: $f->provider.' · '.$f->status->value,
            occurredAt: $f->created_at,
            severity: SystemEventSeverity::Info,
            isFinancial: false,
            meta: $meta,
            sourceKey: 'fulfillment:'.$f->id,
        );
    }
}
