<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FulfillmentStatus;
use App\Enums\SystemEventSeverity;
use App\Models\Fulfillment;
use App\Models\OrderItem;
use App\Models\SystemEvent;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Carbon\Carbon;

/**
 * Operational intelligence: deterministic, threshold-based anomaly detection.
 *
 * Detection is deterministic and threshold-based; all thresholds and windows
 * are read from config/operational_intelligence.php. Idempotency uses time
 * buckets (e.g. 60s for velocity, 10min for refund abuse) so the same window
 * does not create duplicate events.
 *
 * This layer must never block financial flow: it runs only inside DB::afterCommit()
 * at invocation points, never mutates ledger or event recording logic, and never
 * starts or wraps a transaction.
 */
class OperationalIntelligenceService
{
    /**
     * Detect wallet velocity: threshold POSTED transactions within window (same wallet).
     * Records wallet.anomaly.velocity_detected with idempotency by time bucket.
     */
    public function detectWalletVelocity(WalletTransaction $tx): void
    {
        if ($tx->status !== WalletTransaction::STATUS_POSTED) {
            return;
        }

        $wallet = $tx->wallet;
        if ($wallet === null) {
            return;
        }

        $windowSeconds = (int) config('operational_intelligence.wallet_velocity.window_seconds', 60);
        $threshold = (int) config('operational_intelligence.wallet_velocity.threshold', 3);

        $since = Carbon::parse($tx->created_at)->subSeconds($windowSeconds);

        $count = WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('status', WalletTransaction::STATUS_POSTED)
            ->where('created_at', '>=', $since)
            ->where('created_at', '<=', $tx->created_at)
            ->count();

        if ($count < $threshold) {
            return;
        }

        $bucket = (int) floor(Carbon::parse($tx->created_at)->timestamp / $windowSeconds);
        $idempotencySuffix = (string) $bucket;

        app(SystemEventService::class)->record(
            'wallet.anomaly.velocity_detected',
            $wallet,
            null,
            [
                'wallet_id' => $wallet->id,
                'count' => $count,
                'window_seconds' => $windowSeconds,
                'transaction_id' => $tx->id,
            ],
            SystemEventSeverity::Warning,
            false,
            $idempotencySuffix,
        );
    }

    /**
     * Detect refund abuse: threshold refund.approved events within window for same user.
     * Counts via join (system_events -> wallet_transactions -> wallets) to avoid
     * large IN clauses; index-supported filters only. Idempotency by time bucket.
     */
    public function detectRefundAbuse(int $userId): void
    {
        $windowMinutes = (int) config('operational_intelligence.refund_abuse.window_minutes', 10);
        $threshold = (int) config('operational_intelligence.refund_abuse.threshold', 5);

        $since = Carbon::now()->subMinutes($windowMinutes);

        $count = (int) SystemEvent::query()
            ->from('system_events')
            ->where('system_events.event_type', 'refund.approved')
            ->where('system_events.is_financial', false)
            ->where('system_events.created_at', '>=', $since)
            ->where('system_events.entity_type', WalletTransaction::class)
            ->join('wallet_transactions', function ($join): void {
                $join->on('system_events.entity_id', '=', 'wallet_transactions.id')
                    ->where('system_events.entity_type', '=', WalletTransaction::class);
            })
            ->join('wallets', 'wallet_transactions.wallet_id', '=', 'wallets.id')
            ->where('wallets.user_id', $userId)
            ->count();

        if ($count < $threshold) {
            return;
        }

        $user = User::query()->find($userId);
        if ($user === null) {
            return;
        }

        $bucket = (int) floor(Carbon::now()->timestamp / ($windowMinutes * 60));
        $idempotencySuffix = (string) $bucket;

        app(SystemEventService::class)->record(
            'refund.anomaly.pattern_detected',
            $user,
            null,
            [
                'user_id' => $userId,
                'count' => $count,
                'window_minutes' => $windowMinutes,
            ],
            SystemEventSeverity::Warning,
            false,
            $idempotencySuffix,
        );
    }

    /**
     * Detect fulfillment failure spike: threshold failed fulfillments within window
     * for same provider or same product. Records fulfillment.anomaly.failure_spike.
     */
    public function detectFulfillmentFailure(Fulfillment $fulfillment): void
    {
        if ($fulfillment->status !== FulfillmentStatus::Failed) {
            return;
        }

        $windowMinutes = (int) config('operational_intelligence.fulfillment_failure.window_minutes', 30);
        $threshold = (int) config('operational_intelligence.fulfillment_failure.threshold', 5);

        $since = Carbon::now()->subMinutes($windowMinutes);
        $bucket = (int) floor(Carbon::now()->timestamp / ($windowMinutes * 60));

        $countByProvider = Fulfillment::query()
            ->where('provider', $fulfillment->provider)
            ->where('status', FulfillmentStatus::Failed)
            ->where('created_at', '>=', $since)
            ->count();

        if ($countByProvider >= $threshold) {
            app(SystemEventService::class)->record(
                'fulfillment.anomaly.failure_spike',
                $fulfillment,
                null,
                [
                    'provider' => $fulfillment->provider,
                    'count' => $countByProvider,
                    'window_minutes' => $windowMinutes,
                    'scope' => 'provider',
                ],
                SystemEventSeverity::Warning,
                false,
                'provider:'.$fulfillment->provider.':'.$bucket,
            );
        }

        $orderItem = $fulfillment->orderItem;
        if ($orderItem !== null) {
            $countByProduct = Fulfillment::query()
                ->whereIn('order_item_id', OrderItem::query()->where('product_id', $orderItem->product_id)->pluck('id'))
                ->where('status', FulfillmentStatus::Failed)
                ->where('created_at', '>=', $since)
                ->count();

            if ($countByProduct >= $threshold) {
                app(SystemEventService::class)->record(
                    'fulfillment.anomaly.failure_spike',
                    $fulfillment,
                    null,
                    [
                        'product_id' => $orderItem->product_id,
                        'count' => $countByProduct,
                        'window_minutes' => $windowMinutes,
                        'scope' => 'product',
                    ],
                    SystemEventSeverity::Warning,
                    false,
                    'product:'.$orderItem->product_id.':'.$bucket,
                );
            }
        }
    }

    /**
     * Record reconciliation drift (invoked when WalletReconcile detects drift).
     * Records wallet.anomaly.drift_detected, severity critical.
     *
     * @param  array{stored: float, expected: float, diff: float}  $driftMeta
     */
    public function detectReconciliationDrift(Wallet $wallet, array $driftMeta): void
    {
        $date = Carbon::now()->format('Y-m-d');
        app(SystemEventService::class)->record(
            'wallet.anomaly.drift_detected',
            $wallet,
            null,
            [
                'wallet_id' => $wallet->id,
                'user_id' => $wallet->user_id,
                'stored' => $driftMeta['stored'],
                'expected' => $driftMeta['expected'],
                'diff' => $driftMeta['diff'],
            ],
            SystemEventSeverity::Critical,
            false,
            $date,
        );
    }
}
