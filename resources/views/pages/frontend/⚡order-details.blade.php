<?php

use App\Actions\Fulfillments\RetryFulfillment;
use App\Actions\Orders\RefundOrderItem;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::frontend')] class extends Component
{
    public Order $order;
    public ?string $actionMessage = null;

    public function mount(Order $order): void
    {
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        $this->order = $order->load([
            'items.fulfillments',
            'items.product',
            'items.package',
        ]);
    }

    public function render(): View
    {
        return $this->view()->title(__('messages.order_details'));
    }

    public function retryFulfillment(int $fulfillmentId): void
    {
        $this->reset('actionMessage');

        $fulfillment = $this->order->items
            ->flatMap(fn ($item) => $item->fulfillments)
            ->firstWhere('id', $fulfillmentId);

        if ($fulfillment === null) {
            $this->actionMessage = __('messages.retry_not_allowed');
            return;
        }

        app(RetryFulfillment::class)->handle($fulfillment, 'customer', auth()->id());

        $fulfillment->refresh();
        $this->order->load('items.fulfillments');
        $this->actionMessage = $fulfillment->status === FulfillmentStatus::Queued
            ? __('messages.fulfillment_marked_queued')
            : __('messages.retry_not_allowed');
    }

    public function requestRefund(int $fulfillmentId): void
    {
        $this->reset('actionMessage');

        $fulfillment = $this->order->items
            ->flatMap(fn ($item) => $item->fulfillments)
            ->firstWhere('id', $fulfillmentId);

        if ($fulfillment === null) {
            $this->actionMessage = __('messages.refund_not_allowed');
            return;
        }

        try {
            app(RefundOrderItem::class)->handle($fulfillment, auth()->id());
        } catch (ValidationException $exception) {
            $this->actionMessage = collect($exception->errors())->flatten()->first()
                ?? __('messages.refund_not_allowed');
            return;
        }

        $this->order->load('items.fulfillments');
        $this->actionMessage = __('messages.refund_waiting_approval');
    }

    /**
     * @return Collection<int, \App\Models\OrderItem>
     */
    public function getItemsProperty(): Collection
    {
        return $this->order->items;
    }

    protected function statusLabel(?FulfillmentStatus $status): string
    {
        return match ($status) {
            FulfillmentStatus::Completed => __('messages.delivery_completed'),
            FulfillmentStatus::Failed => __('messages.delivery_failed'),
            FulfillmentStatus::Processing, FulfillmentStatus::Queued => __('messages.delivery_preparing'),
            default => __('messages.delivery_preparing'),
        };
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

    private function maskValue(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $length = mb_strlen($value);

        if ($length <= 4) {
            return str_repeat('•', $length);
        }

        $visible = 4;

        return str_repeat('•', $length - $visible).mb_substr($value, -$visible);
    }

    /**
     * @return array<int, array{key: string, masked: string, encoded: string, sensitive: bool}>
     */
    protected function payloadEntries(mixed $payload): array
    {
        $payload = $this->normalizePayload($payload);

        if ($payload === null) {
            return [];
        }

        $forceSensitive = ! is_array($payload);
        $values = is_array($payload) ? $payload : ['value' => $payload];
        $entries = [];

        foreach ($values as $key => $value) {
            $keyLabel = is_string($key) ? $key : (string) $key;
            $valueString = $this->stringifyPayloadValue($value);
            $isSensitive = $forceSensitive || $this->isSensitiveKey($keyLabel);
            $masked = $isSensitive ? $this->maskValue($valueString) : $valueString;

            $entries[] = [
                'key' => $keyLabel,
                'masked' => $masked,
                'encoded' => base64_encode($valueString),
                'sensitive' => $isSensitive,
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

    protected function normalizePayload(mixed $payload): mixed
    {
        if (! is_string($payload)) {
            return $payload;
        }

        $decoded = json_decode($payload, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return $payload;
    }

    protected function isSensitiveKey(string $key): bool
    {
        $sensitive = ['code', 'pin', 'serial', 'token', 'password'];

        return in_array(mb_strtolower($key), $sensitive, true);
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
};
?>

<div class="mx-auto w-full max-w-5xl px-3 py-6 sm:px-0 sm:py-10">
    <div class="mb-4 flex items-center">
        <x-back-button :fallback="route('orders.index')" />
    </div>
    <div class="flex flex-col gap-6">
        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="space-y-1">
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                        {{ __('messages.order_details') }}
                    </flux:heading>
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ $order->order_number }}
                    </flux:text>
                </div>
                <div class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ $order->created_at?->format('M d, Y H:i') ?? '—' }}
                </div>
            </div>

            <div class="mt-4 grid gap-3 text-sm text-zinc-600 dark:text-zinc-400 sm:grid-cols-2">
                <div class="flex items-center justify-between gap-2 rounded-xl border border-zinc-100 bg-zinc-50 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-800/60">
                    <span>{{ __('messages.order') }}</span>
                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">
                        #{{ $order->id }}
                    </span>
                </div>
                <div class="flex items-center justify-between gap-2 rounded-xl border border-zinc-100 bg-zinc-50 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-800/60">
                    <span>{{ __('messages.payment_status') }}</span>
                    <flux:badge color="{{ match ($order->status) {
                        OrderStatus::Fulfilled => 'green',
                        OrderStatus::Failed, OrderStatus::Cancelled => 'red',
                        OrderStatus::Refunded => 'gray',
                        OrderStatus::Paid => 'blue',
                        default => 'amber',
                    } }}">
                        {{ $this->orderStatusLabel($order->status) }}
                    </flux:badge>
                </div>
                <div class="flex items-center justify-between gap-2 rounded-xl border border-zinc-100 bg-zinc-50 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-800/60">
                    <span>{{ __('messages.total') }}</span>
                    @if(\App\Models\WebsiteSetting::getPricesVisible())
                    <span class="font-semibold text-zinc-900 dark:text-zinc-100" dir="ltr">
                        {{ $order->total }} {{ $order->currency }}
                    </span>
                    @else
                    <span class="font-semibold text-zinc-500 dark:text-zinc-400">—</span>
                    @endif
                </div>
                <div class="flex items-center justify-between gap-2 rounded-xl border border-zinc-100 bg-zinc-50 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-800/60">
                    <span>{{ __('messages.created') }}</span>
                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ $order->created_at?->format('M d, Y H:i') ?? '—' }}
                    </span>
                </div>
            </div>

        </section>

        <section class="space-y-4">
            @foreach ($this->items as $item)
                @php
                    $fulfillments = $item->fulfillments->sortBy('id')->values();
                    $itemStatus = $item->aggregateFulfillmentStatus($fulfillments);
                    $requirementsEntries = $this->requirementsEntries($item->requirements_payload);
                @endphp

                <div id="item-{{ $item->id }}" class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900" wire:key="order-item-{{ $item->id }}">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="space-y-1">
                            <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                {{ $item->product?->name ?? $item->name }}
                            </div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __('messages.quantity') }}: {{ $item->quantity }}
                            </div>
                            @if ($item->package?->name)
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $item->package->name }}
                                </div>
                            @endif
                        </div>
                        <flux:badge color="{{ $itemStatus === FulfillmentStatus::Completed ? 'green' : ($itemStatus === FulfillmentStatus::Failed ? 'red' : ($itemStatus === FulfillmentStatus::Processing ? 'amber' : 'gray')) }}">
                            {{ $this->statusLabel($itemStatus) }}
                        </flux:badge>
                    </div>

                    <div class="mt-4 grid gap-2 text-xs text-zinc-500 dark:text-zinc-400 sm:grid-cols-3">
                        <div class="flex items-center justify-between gap-2 rounded-lg border border-zinc-100 bg-zinc-50 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-800/60">
                            <span>{{ __('messages.unit_price') }}</span>
                            @if(\App\Models\WebsiteSetting::getPricesVisible())
                            <span class="font-semibold text-zinc-900 dark:text-zinc-100" dir="ltr">
                                {{ $item->unit_price }} {{ $order->currency }}
                            </span>
                            @else
                            <span class="font-semibold text-zinc-500 dark:text-zinc-400">—</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between gap-2 rounded-lg border border-zinc-100 bg-zinc-50 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-800/60">
                            <span>{{ __('messages.line_total') }}</span>
                            @if(\App\Models\WebsiteSetting::getPricesVisible())
                            <span class="font-semibold text-zinc-900 dark:text-zinc-100" dir="ltr">
                                {{ $item->line_total }} {{ $order->currency }}
                            </span>
                            @else
                            <span class="font-semibold text-zinc-500 dark:text-zinc-400">—</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between gap-2 rounded-lg border border-zinc-100 bg-zinc-50 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-800/60">
                            <span>{{ __('messages.payment_status') }}</span>
                            <span class="font-semibold text-zinc-900 dark:text-zinc-100">
                                {{ $this->orderStatusLabel($order->status) }}
                            </span>
                        </div>
                    </div>

                    @if ($requirementsEntries !== [])
                        <div class="mt-4 rounded-xl border border-zinc-100 bg-zinc-50 p-3 text-xs text-zinc-600 dark:border-zinc-800 dark:bg-zinc-800/60 dark:text-zinc-300">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                {{ __('messages.requirements') }}
                            </div>
                            <div class="mt-2 grid gap-2">
                                @foreach ($requirementsEntries as $entry)
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="text-zinc-500 dark:text-zinc-400">{{ $entry['key'] }}</span>
                                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">
                                            {{ $entry['value'] }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="mt-4 space-y-3">
                        @if ($fulfillments->isEmpty())
                            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                                {{ __('messages.delivery_preparing_hint') }}
                            </flux:text>
                        @else
                            @foreach ($fulfillments as $index => $fulfillment)
                                @php
                                    $payload = data_get($fulfillment->meta, 'delivered_payload');
                                    $payloadEntries = $this->payloadEntries($payload);
                                    $refundStatus = data_get($fulfillment->meta, 'refund.status');
                                    $isRefundPending = $refundStatus === WalletTransaction::STATUS_PENDING;
                                    $isRefundPosted = $refundStatus === WalletTransaction::STATUS_POSTED;
                                    $isRefundRejected = $refundStatus === WalletTransaction::STATUS_REJECTED;
                                    $retryRequested = data_get($fulfillment->meta, 'last_retry_actor') === 'customer'
                                        && (int) data_get($fulfillment->meta, 'retry_count', 0) > 0;
                                    $showActions = $fulfillment->status === FulfillmentStatus::Failed
                                        && ! $isRefundPending
                                        && ! $isRefundPosted;
                                @endphp

                                <div class="rounded-xl border border-zinc-100 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-800/60" wire:key="fulfillment-unit-{{ $fulfillment->id }}">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                                {{ __('messages.unit') }} {{ $index + 1 }}
                                            </div>
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                #{{ $fulfillment->id }}
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <flux:badge color="{{ $fulfillment->status === FulfillmentStatus::Completed ? 'green' : ($fulfillment->status === FulfillmentStatus::Failed ? 'red' : 'amber') }}">
                                                {{ $this->statusLabel($fulfillment->status) }}
                                            </flux:badge>
                                            @if ($isRefundPending)
                                                <flux:badge color="amber">{{ __('messages.refund_requested') }}</flux:badge>
                                            @elseif ($isRefundPosted)
                                                <flux:badge color="green">{{ __('messages.refunded') }}</flux:badge>
                                            @elseif ($isRefundRejected)
                                                <flux:badge color="red">{{ __('messages.refund_rejected') }}</flux:badge>
                                            @endif
                                            @if ($retryRequested && $fulfillment->status === FulfillmentStatus::Queued)
                                                <flux:badge color="blue">{{ __('messages.retry_requested') }}</flux:badge>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        @if ($fulfillment->status === FulfillmentStatus::Completed)
                                            @if ($payloadEntries !== [])
                                                <div
                                                    class="space-y-2"
                                                    x-data="{
                                                        revealed: false,
                                                        copiedIndex: null,
                                                        entries: @js($payloadEntries),
                                                        decode(encoded) {
                                                            try {
                                                                const bytes = Uint8Array.from(atob(encoded), c => c.charCodeAt(0));
                                                                if (typeof TextDecoder === 'undefined') {
                                                                    return atob(encoded);
                                                                }
                                                                return new TextDecoder().decode(bytes);
                                                            } catch (e) {
                                                                return '';
                                                            }
                                                        },
                                                        async copyEntry(entry, index) {
                                                            try {
                                                                await navigator.clipboard.writeText(this.decode(entry.encoded));
                                                                this.copiedIndex = index;
                                                                setTimeout(() => this.copiedIndex = null, 1500);
                                                            } catch (e) {
                                                            }
                                                        }
                                                    }"
                                                >
                                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                                                            {{ __('messages.delivery_payload') }}
                                                        </flux:text>
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <flux:button
                                                                variant="ghost"
                                                                size="xs"
                                                                x-on:click="revealed = !revealed"
                                                            >
                                                                <span x-show="!revealed">{{ __('messages.reveal') }}</span>
                                                                <span x-show="revealed">{{ __('messages.hide') }}</span>
                                                            </flux:button>
                                                        </div>
                                                    </div>
                                                    <div class="grid gap-2 rounded-xl border border-zinc-200 bg-white p-3 text-xs text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                                                        <template x-for="(entry, entryIndex) in entries" :key="entryIndex">
                                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                                <div class="flex flex-col gap-1">
                                                                    <span
                                                                        class="text-[11px] uppercase tracking-wide"
                                                                        x-bind:class="entry.sensitive ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-500 dark:text-zinc-400'"
                                                                        x-text="entry.key"
                                                                    ></span>
                                                                    <span class="font-mono text-xs text-zinc-900 dark:text-zinc-100">
                                                                        <span x-text="revealed ? decode(entry.encoded) : entry.masked"></span>
                                                                    </span>
                                                                </div>
                                                                <flux:button
                                                                    x-show="entry.sensitive"
                                                                    variant="ghost"
                                                                    size="xs"
                                                                    x-on:click="copyEntry(entry, entryIndex)"
                                                                >
                                                                    <flux:icon.document-duplicate x-show="copiedIndex !== entryIndex" variant="outline"></flux:icon.document-duplicate>
                                                                    <flux:icon.check x-show="copiedIndex === entryIndex" variant="solid" class="text-green-500"></flux:icon.check>
                                                                    {{ __('messages.copy_to_clipboard') }}
                                                                </flux:button>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            @else
                                                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                                                    {{ __('messages.no_payload') }}
                                                </flux:text>
                                            @endif
                                        @elseif ($fulfillment->status === FulfillmentStatus::Failed)
                                            <div class="space-y-3">
                                                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                                                    {{ __('messages.delivery_failed_contact_support') }}
                                                </flux:text>
                                                @if ($isRefundPending)
                                                    <flux:text class="text-xs font-semibold text-amber-600 dark:text-amber-400">
                                                        {{ __('messages.refund_waiting_approval') }}
                                                    </flux:text>
                                                @elseif ($isRefundPosted)
                                                    <flux:text class="text-xs font-semibold text-green-600 dark:text-green-400">
                                                        {{ __('messages.refund_completed') }}
                                                    </flux:text>
                                                @elseif ($isRefundRejected)
                                                    <flux:text class="text-xs font-semibold text-red-600 dark:text-red-400">
                                                        {{ __('messages.refund_rejected') }}
                                                    </flux:text>
                                                @endif
                                                @if ($showActions)
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <flux:button
                                                            variant="outline"
                                                            size="sm"
                                                            wire:click="requestRefund({{ $fulfillment->id }})"
                                                            wire:loading.attr="disabled"
                                                            wire:target="requestRefund({{ $fulfillment->id }})"
                                                        >
                                                            {{ __('messages.request_refund') }}
                                                        </flux:button>
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                                                {{ __('messages.delivery_preparing_hint') }}
                                            </flux:text>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            @endforeach
        </section>
    </div>
</div>
