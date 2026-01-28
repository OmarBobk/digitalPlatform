<?php

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::frontend')] class extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        $this->order = $order->load([
            'items.fulfillment',
            'items.product',
            'items.package',
        ]);
    }

    public function render(): View
    {
        return $this->view()->title(__('messages.order_details'));
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
                    <span class="font-semibold text-zinc-900 dark:text-zinc-100" dir="ltr">
                        {{ $order->total }} {{ $order->currency }}
                    </span>
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
                    $fulfillment = $item->fulfillment;
                    $payload = data_get($fulfillment?->meta, 'delivered_payload');
                    $payloadEntries = $this->payloadEntries($payload);
                    $requirementsEntries = $this->requirementsEntries($item->requirements_payload);
                @endphp

                <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900" wire:key="order-item-{{ $item->id }}">
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
                        <flux:badge color="{{ $fulfillment?->status === FulfillmentStatus::Completed ? 'green' : ($fulfillment?->status === FulfillmentStatus::Failed ? 'red' : 'amber') }}">
                            {{ $this->statusLabel($fulfillment?->status) }}
                        </flux:badge>
                    </div>

                    <div class="mt-4 grid gap-2 text-xs text-zinc-500 dark:text-zinc-400 sm:grid-cols-3">
                        <div class="flex items-center justify-between gap-2 rounded-lg border border-zinc-100 bg-zinc-50 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-800/60">
                            <span>{{ __('messages.unit_price') }}</span>
                            <span class="font-semibold text-zinc-900 dark:text-zinc-100" dir="ltr">
                                {{ $item->unit_price }} {{ $order->currency }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between gap-2 rounded-lg border border-zinc-100 bg-zinc-50 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-800/60">
                            <span>{{ __('messages.line_total') }}</span>
                            <span class="font-semibold text-zinc-900 dark:text-zinc-100" dir="ltr">
                                {{ $item->line_total }} {{ $order->currency }}
                            </span>
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

                    <div class="mt-4">
                        @if ($fulfillment?->status === FulfillmentStatus::Completed)
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
                                    <div class="grid gap-2 rounded-xl border border-zinc-200 bg-zinc-50 p-3 text-xs text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                                        <template x-for="(entry, index) in entries" :key="index">
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
                                                    x-on:click="copyEntry(entry, index)"
                                                >
                                                    <flux:icon.document-duplicate x-show="copiedIndex !== index" variant="outline"></flux:icon.document-duplicate>
                                                    <flux:icon.check x-show="copiedIndex === index" variant="solid" class="text-green-500"></flux:icon.check>
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
                        @elseif ($fulfillment?->status === FulfillmentStatus::Failed)
                            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                                {{ __('messages.delivery_failed_contact_support') }}
                            </flux:text>
                        @else
                            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                                {{ __('messages.delivery_preparing_hint') }}
                            </flux:text>
                        @endif
                    </div>
                </div>
            @endforeach
        </section>
    </div>
</div>
