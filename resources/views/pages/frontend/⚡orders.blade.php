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
            ->with(['items.fulfillments'])
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

    public function render(): View
    {
        return $this->view()->title(__('messages.orders'));
    }
};
?>

<div class="mx-auto w-full max-w-6xl px-3 py-6 sm:px-0 sm:py-10">
    <div class="mb-4 flex items-center">
        <x-back-button />
    </div>

    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="space-y-1">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                    {{ __('messages.orders') }}
                </flux:heading>
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('messages.orders_intro') }}
                </flux:text>
            </div>
        </div>

        <div class="mt-4 overflow-hidden rounded-2xl border border-zinc-100 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                @if ($this->orders->isEmpty())
                    <div class="flex flex-col items-center justify-center gap-2 px-6 py-16 text-center">
                        <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">
                            {{ __('messages.no_orders') }}
                        </flux:heading>
                        <flux:text class="text-zinc-600 dark:text-zinc-400">
                            {{ __('messages.no_orders_hint') }}
                        </flux:text>
                    </div>
                @else
                    <table class="min-w-full divide-y divide-zinc-100 text-sm dark:divide-zinc-800" data-test="orders-table">
                        <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                            <tr>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.order_number') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.created') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.total') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.payment_status') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.fulfillment_summary') }}</th>
                                <th class="px-5 py-3 text-end font-semibold">{{ __('messages.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->orders as $order)
                                @php
                                    $summary = $this->fulfillmentSummary($order);
                                @endphp
                                <tr class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/60" wire:key="order-{{ $order->id }}">
                                    <td class="px-5 py-4">
                                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">
                                            {{ $order->order_number }}
                                        </div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            #{{ $order->id }}
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                                        {{ $order->created_at?->format('M d, Y H:i') ?? '—' }}
                                    </td>
                                    <td class="px-5 py-4 text-zinc-700 dark:text-zinc-200" dir="ltr">
                                        {{ $order->total }} {{ $order->currency }}
                                    </td>
                                    <td class="px-5 py-4">
                                        <flux:badge color="{{ $this->orderStatusColor($order->status) }}">
                                            {{ $this->orderStatusLabel($order->status) }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-5 py-4">
                                        <flux:badge color="{{ $summary['color'] }}">
                                            {{ $summary['label'] }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-5 py-4 text-end">
                                        <a
                                            href="{{ route('orders.show', $order->order_number) }}"
                                            wire:navigate
                                            class="text-sm font-semibold text-zinc-900 hover:underline dark:text-zinc-100"
                                        >
                                            {{ __('messages.view_order') }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="mt-4 border-t border-zinc-100 px-5 py-4 dark:border-zinc-800">
            {{ $this->orders->links() }}
        </div>
    </section>
</div>
