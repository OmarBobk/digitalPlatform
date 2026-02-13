<?php

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::frontend')] class extends Component
{
    use WithPagination;

    public int $perPage = 10;

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
    }

    public function getOrdersProperty(): LengthAwarePaginator
    {
        return Order::query()
            ->where('user_id', auth()->id())
            ->with(['items.fulfillments', 'items.product', 'items.package'])
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

    protected function orderStatusColor(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Fulfilled => 'green',
            OrderStatus::Failed, OrderStatus::Cancelled => 'red',
            OrderStatus::Refunded => 'gray',
            OrderStatus::Paid => 'blue',
            default => 'amber',
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

    protected function formatAmount(float|string $amount, string $currency): string
    {
        $value = number_format((float) $amount, 2);

        return strtoupper($currency) === 'USD' ? '$' . $value : $value . ' ' . $currency;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<int, array{key: string, value: string}>
     */
    protected function requirementsEntries(?array $payload): array
    {
        if ($payload === null || $payload === []) {
            return [];
        }

        $entries = [];

        foreach ($payload as $key => $value) {
            $entries[] = [
                'key' => is_string($key) ? $key : (string) $key,
                'value' => $this->stringifyPayloadValue($value),
            ];
        }

        return $entries;
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

    <section class="space-y-6">
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
                    @php
                        $summary = $this->fulfillmentSummary($order);
                    @endphp
                    <article
                        wire:key="order-{{ $order->id }}"
                        class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900"
                    >
                        {{-- Order header --}}
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-100 bg-zinc-50/50 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-800/30 sm:px-5">
                            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                                <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $order->order_number }}</span>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $order->created_at?->format('M d, Y H:i') ?? '—' }}
                                </span>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge color="{{ $this->orderStatusColor($order->status) }}">
                                    {{ $this->orderStatusLabel($order->status) }}
                                </flux:badge>
                                <flux:badge color="{{ $summary['color'] }}">
                                    {{ $summary['label'] }}
                                </flux:badge>
                                <span class="font-semibold text-zinc-900 dark:text-zinc-100" dir="ltr">
                                    {{ $this->formatAmount($order->total, $order->currency) }}
                                </span>
                            </div>
                        </div>

                        {{-- Order items (units when quantity > 1) --}}
                        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($order->items as $item)
                                @php
                                    $fulfillments = $item->fulfillments->sortBy('id')->values();
                                    $itemStatus = $item->aggregateFulfillmentStatus($fulfillments);
                                    $itemStatusColor = $itemStatus === \App\Enums\FulfillmentStatus::Completed ? 'green' : ($itemStatus === \App\Enums\FulfillmentStatus::Failed ? 'red' : ($itemStatus === \App\Enums\FulfillmentStatus::Processing ? 'amber' : 'gray'));
                                    $statusLabel = match ($itemStatus) {
                                        \App\Enums\FulfillmentStatus::Completed => __('messages.delivery_completed'),
                                        \App\Enums\FulfillmentStatus::Failed => __('messages.delivery_failed'),
                                        \App\Enums\FulfillmentStatus::Processing, \App\Enums\FulfillmentStatus::Queued => __('messages.delivery_preparing'),
                                        default => __('messages.delivery_preparing'),
                                    };
                                    $requirementsEntries = $this->requirementsEntries($item->requirements_payload);
                                @endphp
                                @if ($item->quantity > 1 && $fulfillments->isNotEmpty())
                                    @foreach ($fulfillments as $index => $fulfillment)
                                        @php
                                            $unitStatus = $fulfillment->status;
                                            $unitStatusColor = $unitStatus === \App\Enums\FulfillmentStatus::Completed ? 'green' : ($unitStatus === \App\Enums\FulfillmentStatus::Failed ? 'red' : 'amber');
                                            $unitStatusLabel = match ($unitStatus) {
                                                \App\Enums\FulfillmentStatus::Completed => __('messages.delivery_completed'),
                                                \App\Enums\FulfillmentStatus::Failed => __('messages.delivery_failed'),
                                                \App\Enums\FulfillmentStatus::Processing, \App\Enums\FulfillmentStatus::Queued => __('messages.delivery_preparing'),
                                                default => __('messages.delivery_preparing'),
                                            };
                                        @endphp
                                        <div class="flex items-center gap-4 px-4 py-3 sm:px-5 sm:py-4">
                                            <div class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800">
                                                @if ($item->package?->image)
                                                    <img
                                                        src="{{ asset($item->package->image) }}"
                                                        alt="{{ $item->package->name }}"
                                                        class="h-full w-full object-contain"
                                                        loading="lazy"
                                                    />
                                                @else
                                                    <flux:icon icon="cube" class="size-6 text-zinc-400 dark:text-zinc-500" />
                                                @endif
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                                    {{ $item->product?->name ?? $item->name }}
                                                </div>
                                                @if ($item->package?->name)
                                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                        {{ $item->package->name }}
                                                    </div>
                                                @endif
                                                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                                    {{__('messages.order_id')}}: #{{ $item->id }}U{{ $index + 1 }} · {{ __('messages.unit') }} {{ $index + 1 }} {{ __('messages.of') }} {{ $item->quantity }}@if(\App\Models\WebsiteSetting::getPricesVisible()) · {{ $this->formatAmount($item->unit_price, $order->currency) }}@endif
                                                </div>
                                                @if ($requirementsEntries !== [])
                                                    <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs">
                                                        @foreach ($requirementsEntries as $entry)
                                                            <span class="text-zinc-600 dark:text-zinc-300"><span class="text-zinc-500 dark:text-zinc-400">{{ $entry['key'] }}:</span> {{ $entry['value'] }}</span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="flex shrink-0 flex-col items-end gap-1">
                                                @if(\App\Models\WebsiteSetting::getPricesVisible())
                                                <span class="font-semibold text-zinc-900 dark:text-zinc-100" dir="ltr">
                                                    {{ $this->formatAmount($item->unit_price, $order->currency) }}
                                                </span>
                                                @else
                                                <span class="font-semibold text-zinc-500 dark:text-zinc-400">—</span>
                                                @endif
                                                <flux:badge color="{{ $unitStatusColor }}" class="text-xs">
                                                    {{ $unitStatusLabel }}
                                                </flux:badge>
                                            </div>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="flex items-center gap-4 px-4 py-3 sm:px-5 sm:py-4">
                                        <div class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800">
                                            @if ($item->package?->image)
                                                <img
                                                    src="{{ asset($item->package->image) }}"
                                                    alt="{{ $item->package->name }}"
                                                    class="h-full w-full object-contain"
                                                    loading="lazy"
                                                />
                                            @else
                                                <flux:icon icon="cube" class="size-6 text-zinc-400 dark:text-zinc-500" />
                                            @endif
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ $item->product?->name ?? $item->name }}
                                            </div>
                                            @if ($item->package?->name)
                                                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                    {{ $item->package->name }}
                                                </div>
                                            @endif
                                            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                                {{__('messages.order_id')}}: #{{ $item->id }} · {{ __('messages.quantity') }}: {{ $item->quantity }}@if(\App\Models\WebsiteSetting::getPricesVisible()) × {{ $this->formatAmount($item->unit_price, $order->currency) }}@endif
                                            </div>
                                            @if ($requirementsEntries !== [])
                                                <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs">
                                                    @foreach ($requirementsEntries as $entry)
                                                        <span class="text-zinc-600 dark:text-zinc-300"><span class="text-zinc-500 dark:text-zinc-400">{{ $entry['key'] }}:</span> {{ $entry['value'] }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <div class="flex shrink-0 flex-col items-end gap-1">
                                            @if(\App\Models\WebsiteSetting::getPricesVisible())
                                            <span class="font-semibold text-zinc-900 dark:text-zinc-100" dir="ltr">
                                                {{ $this->formatAmount($item->line_total, $order->currency) }}
                                            </span>
                                            @else
                                            <span class="font-semibold text-zinc-500 dark:text-zinc-400">—</span>
                                            @endif
                                            <flux:badge color="{{ $itemStatusColor }}" class="text-xs">
                                                {{ $statusLabel }}
                                            </flux:badge>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                    </article>
                @endforeach
            </div>

            <div class="pt-2">
                {{ $this->orders->links() }}
            </div>
        @endif
    </section>
</div>
