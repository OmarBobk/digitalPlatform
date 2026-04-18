<?php

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductAmountMode;
use App\Actions\Orders\RefundOrderItem;
use App\Support\FrontendMoney;
use App\Support\OrderRequirementLabels;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\WalletTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Masmerise\Toaster\Toastable;

new #[Layout('layouts::frontend')] class extends Component
{
    use Toastable;
    use WithPagination;

    public int $perPage = 10;

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
    }

    public function requestRefundForOrder(int $orderId): void
    {
        $userId = auth()->id();
        if ($userId === null) {
            return;
        }

        $order = Order::query()
            ->where('user_id', $userId)
            ->with(['items.fulfillments'])
            ->find($orderId);

        if ($order === null) {
            $this->error(__('messages.refund_not_allowed'));

            return;
        }

        $eligible = $order->items
            ->flatMap(fn (OrderItem $item) => $item->fulfillments)
            ->filter(function ($fulfillment) {
                if ($fulfillment->status !== FulfillmentStatus::Failed) {
                    return false;
                }

                $refundStatus = data_get($fulfillment->meta, 'refund.status');

                return ! in_array($refundStatus, [WalletTransaction::STATUS_PENDING, WalletTransaction::STATUS_POSTED], true);
            })
            ->sortBy('id')
            ->values();

        if ($eligible->isEmpty()) {
            $this->error(__('messages.refund_not_allowed'));

            return;
        }

        $firstError = null;

        foreach ($eligible as $fulfillment) {
            try {
                app(RefundOrderItem::class)->handle($fulfillment, (int) $userId);
            } catch (ValidationException $exception) {
                $firstError ??= collect($exception->errors())->flatten()->first()
                    ?? __('messages.refund_not_allowed');

                break;
            }
        }

        if ($firstError !== null) {
            $this->error($firstError);

            return;
        }

        $this->success(__('messages.refund_waiting_approval'));
    }

    public function getOrdersProperty(): LengthAwarePaginator
    {
        return Order::query()
            ->where('user_id', auth()->id())
            ->with(['items.fulfillments', 'items.product', 'items.package.requirements'])
            ->latest('created_at')
            ->paginate($this->perPage);
    }

    protected function orderStatusLabel(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::PendingPayment => __('messages.order_status_pending_payment'),
            OrderStatus::Paid => __('messages.order_status_paid'),
            OrderStatus::Processing => __('messages.order_status_processing'),
            OrderStatus::Fulfilled => __('messages.order_status_fulfilled'),
            OrderStatus::Failed => __('messages.order_status_failed'),
            OrderStatus::Refunded => __('messages.order_status_refunded'),
            OrderStatus::Cancelled => __('messages.order_status_cancelled'),
        };
    }

    /**
     * @return array{label: string, color: string}
     */
    protected function fulfillmentSummary(Order $order): array
    {
        $items = $order->items;

        if ($items->isEmpty()) {
            return [
                'label' => __('messages.fulfillment_status_queued'),
                'color' => 'gray',
            ];
        }

        $fulfillments = $items->flatMap(fn ($item) => $item->fulfillments);
        $hasEmpty = $items->contains(fn ($item) => $item->fulfillments->isEmpty());

        if ($hasEmpty || $fulfillments->isEmpty()) {
            return [
                'label' => __('messages.fulfillment_status_queued'),
                'color' => 'gray',
            ];
        }

        $hasFailed = $fulfillments->contains(fn ($fulfillment) => $fulfillment->status === FulfillmentStatus::Failed);
        $hasProcessing = $fulfillments->contains(fn ($fulfillment) => $fulfillment->status === FulfillmentStatus::Processing);
        $hasQueued = $fulfillments->contains(fn ($fulfillment) => $fulfillment->status === FulfillmentStatus::Queued);
        $allCompleted = $fulfillments->every(fn ($fulfillment) => $fulfillment->status === FulfillmentStatus::Completed);

        if ($hasFailed) {
            return [
                'label' => __('messages.fulfillment_status_failed'),
                'color' => 'red',
            ];
        }

        if ($hasProcessing) {
            return [
                'label' => __('messages.fulfillment_status_processing'),
                'color' => 'amber',
            ];
        }

        if ($hasQueued) {
            return [
                'label' => __('messages.fulfillment_status_queued'),
                'color' => 'gray',
            ];
        }

        if ($allCompleted) {
            return [
                'label' => __('messages.delivery_completed'),
                'color' => 'green',
            ];
        }

        return [
            'label' => __('messages.fulfillment_status_queued'),
            'color' => 'gray',
        ];
    }

    /**
     * Single primary status for the order card (paid=blue, processing=amber, completed=green).
     *
     * @return array{label: string, color: string, progress: int}
     */
    protected function orderUnifiedStatus(Order $order): array
    {
        $fulfillment = $this->fulfillmentSummary($order);

        if (in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Failed], true)) {
            return [
                'label' => $this->orderStatusLabel($order->status),
                'color' => 'red',
                'progress' => 0,
            ];
        }

        if ($fulfillment['color'] === 'red') {
            return [
                'label' => $fulfillment['label'],
                'color' => 'red',
                'progress' => 100,
            ];
        }

        if ($order->status === OrderStatus::Refunded) {
            return [
                'label' => $this->orderStatusLabel($order->status),
                'color' => 'gray',
                'progress' => 100,
            ];
        }

        if ($order->status === OrderStatus::PendingPayment) {
            return [
                'label' => $this->orderStatusLabel($order->status),
                'color' => 'amber',
                'progress' => 25,
            ];
        }

        if ($order->status === OrderStatus::Fulfilled || $fulfillment['color'] === 'green') {
            return [
                'label' => $order->status === OrderStatus::Fulfilled
                    ? $this->orderStatusLabel(OrderStatus::Fulfilled)
                    : $fulfillment['label'],
                'color' => 'green',
                'progress' => 100,
            ];
        }

        if ($order->status === OrderStatus::Processing || $fulfillment['color'] === 'amber') {
            return [
                'label' => $order->status === OrderStatus::Processing
                    ? $this->orderStatusLabel(OrderStatus::Processing)
                    : $fulfillment['label'],
                'color' => 'amber',
                'progress' => 75,
            ];
        }

        if ($order->status === OrderStatus::Paid) {
            return [
                'label' => $this->orderStatusLabel(OrderStatus::Paid),
                'color' => 'blue',
                'progress' => 50,
            ];
        }

        return [
            'label' => $fulfillment['label'],
            'color' => $fulfillment['color'] === 'gray' ? 'amber' : $fulfillment['color'],
            'progress' => 40,
        ];
    }

    /**
     * @return array{lines: int, units: int}
     */
    protected function orderCardSummary(Order $order): array
    {
        return [
            'lines' => $order->items->count(),
            'units' => (int) $order->items->sum('quantity'),
        ];
    }

    /**
     * Refund status / action for orders with at least one failed fulfillment (list card).
     *
     * @return array{kind: 'badge', label: string, color: string}|array{kind: 'action', label: string, orderId: int}|null
     */
    protected function orderCardRefundSummary(Order $order): ?array
    {
        $failed = $order->items
            ->flatMap(fn (OrderItem $item) => $item->fulfillments)
            ->filter(fn ($fulfillment) => $fulfillment->status === FulfillmentStatus::Failed);

        if ($failed->isEmpty()) {
            return null;
        }

        $statuses = $failed->map(fn ($fulfillment) => data_get($fulfillment->meta, 'refund.status'));

        if ($statuses->every(fn ($status) => $status === WalletTransaction::STATUS_POSTED)) {
            return [
                'kind' => 'badge',
                'label' => __('messages.refunded'),
                'color' => 'green',
            ];
        }

        if ($statuses->contains(fn ($status) => $status === WalletTransaction::STATUS_PENDING)) {
            return [
                'kind' => 'badge',
                'label' => __('messages.refund_requested'),
                'color' => 'amber',
            ];
        }

        return [
            'kind' => 'action',
            'label' => __('messages.refund'),
            'orderId' => $order->id,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildOrderCardLines(Order $order): array
    {
        $showPrices = \App\Models\WebsiteSetting::getPricesVisible();
        $out = [];

        foreach ($order->items as $item) {
            $productName = $item->product?->name ?? $item->name;
            $packageName = $item->package?->name;
            $showPackage = $packageName !== null && $packageName !== $productName;

            $idMetaLabel = OrderRequirementLabels::fallbackLabel('id');
            $playerId = null;
            foreach ($this->requirementsEntries($item->requirements_payload, $item) as $entry) {
                if (strtolower($entry['key']) === 'id') {
                    $playerId = $entry['value'];
                    $idMetaLabel = $entry['label'];
                    break;
                }
            }

            $metaParts = [];
            $metaParts[] = __('messages.quantity').' '.$item->quantity;
            if ($showPrices) {
                $metaParts[] = $this->formatAmount($item->unit_price, $order->currency).' / '.__('messages.unit');
            }
            if ($playerId !== null) {
                $metaParts[] = $idMetaLabel.': '.$playerId;
            }

            $showLinePrice = $this->shouldShowLineItemPrice($order, $item);
            $lineTotalFormatted = ($showPrices && $showLinePrice) ? $this->formatAmount($item->line_total, $order->currency) : null;

            $customAmount = null;
            if (($item->amount_mode ?? ProductAmountMode::Fixed) === ProductAmountMode::Custom && $item->requested_amount !== null) {
                $customAmount = __('messages.order_item_purchased_amount').': '.number_format((int) $item->requested_amount)
                    .(($item->amount_unit_label !== null && $item->amount_unit_label !== '') ? ' '.$item->amount_unit_label : '');
            }

            $fulfillments = $item->fulfillments->sortBy('id')->values();
            $units = [];
            if ($item->quantity > 1 && $fulfillments->isNotEmpty()) {
                foreach ($fulfillments as $index => $_) {
                    $units[] = [
                        'meta' => __('messages.order_id').': #'.$item->id.'U'.($index + 1).' · '.__('messages.unit').' '.($index + 1).' / '.$item->quantity,
                    ];
                }
            }

            $out[] = [
                'title' => $productName,
                'subtitle' => $showPackage ? $packageName : null,
                'meta' => implode(' · ', $metaParts),
                'custom_amount' => $customAmount,
                'line_total' => $lineTotalFormatted,
                'image' => $item->package?->image,
                'expandable_units' => $units !== [],
                'units' => $units,
            ];
        }

        return $out;
    }

    protected function formatAmount(float|string $amount, string $currency): string
    {
        return FrontendMoney::for(auth()->user())->format($amount, $currency, 2);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<int, array{key: string, label: string, value: string}>
     */
    protected function requirementsEntries(?array $payload, ?OrderItem $item = null): array
    {
        if ($payload === null || $payload === []) {
            return [];
        }

        $entries = [];

        foreach ($payload as $key => $value) {
            $keyString = is_string($key) ? $key : (string) $key;
            $entries[] = [
                'key' => $keyString,
                'label' => OrderRequirementLabels::labelForKey($item, $keyString),
                'value' => $this->stringifyPayloadValue($value),
            ];
        }

        return $entries;
    }

    /**
     * Hides the line-level total when it duplicates the order header (single line item, quantity one).
     */
    protected function shouldShowLineItemPrice(Order $order, OrderItem $item): bool
    {
        if ($order->items->count() !== 1) {
            return true;
        }

        return $item->quantity !== 1;
    }

    protected function stringifyPayloadValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        if (is_null($value)) {
            return 'null';
        }

        return (string) $value;
    }

    public function render(): View
    {
        return $this->view()->title(__('messages.orders'));
    }
};
?>

<div class="mx-auto w-full max-w-4xl px-3 py-6 sm:px-0 sm:py-10">
    <div class="mb-6 flex items-center">
        <x-back-button />
    </div>

    <section class="space-y-8">
        <div class="space-y-1">
            <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                {{ __('messages.orders') }}
            </flux:heading>
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('messages.orders_intro') }}
            </flux:text>
        </div>

        @if ($this->orders->isEmpty())
            <div class="flex flex-col items-center justify-center gap-4 rounded-2xl border border-dashed border-zinc-200 px-6 py-16 text-center dark:border-zinc-700">
                <div class="flex size-16 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon icon="shopping-bag" class="size-8 text-zinc-400 dark:text-zinc-500" />
                </div>
                <div class="space-y-1">
                    <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">
                        {{ __('messages.no_orders') }}
                    </flux:heading>
                    <flux:text class="text-zinc-600 dark:text-zinc-400">
                        {{ __('messages.no_orders_hint') }}
                    </flux:text>
                </div>
                <flux:button
                    variant="primary"
                    icon="home"
                    href="{{ route('home') }}"
                    wire:navigate
                    class="!bg-accent !text-accent-foreground hover:!bg-accent-hover"
                >
                    {{ __('messages.homepage') }}
                </flux:button>
            </div>
        @else
            <div class="space-y-6" data-test="orders-list">
                @foreach ($this->orders as $order)
                    <div wire:key="order-{{ $order->id }}">
                        <x-order-card
                            :href="route('orders.show', $order->order_number)"
                            :formatted-total="$this->formatAmount($order->total, $order->currency)"
                            :order-number="$order->order_number"
                            :formatted-date="$order->created_at?->format('M d, Y H:i') ?? '—'"
                            :status="$this->orderUnifiedStatus($order)"
                            :summary="$this->orderCardSummary($order)"
                            :lines="$this->buildOrderCardLines($order)"
                            :show-prices="\App\Models\WebsiteSetting::getPricesVisible()"
                            :refund-summary="$this->orderCardRefundSummary($order)"
                        />
                    </div>
                @endforeach
            </div>

            <div class="pt-2">
                {{ $this->orders->links() }}
            </div>
        @endif
    </section>
</div>
