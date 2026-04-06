@props([
    'href',
    'formattedTotal',
    'orderNumber',
    'formattedDate',
    /** @var array{label: string, color: string, progress: int} $status */
    'status',
    /** @var array{lines: int, units: int} $summary */
    'summary',
    /** @var array<int, array<string, mixed>> $lines */
    'lines',
    'showPrices' => true,
    /** @var array{kind: 'badge', label: string, color: string}|array{kind: 'action', label: string, orderId: int}|null $refundSummary */
    'refundSummary' => null,
])

@php
    $progressWidth = max(0, min(100, $status['progress'] ?? 0));
    $progressTint = match ($status['color'] ?? 'zinc') {
        'green' => 'bg-emerald-500 dark:bg-emerald-400',
        'blue' => 'bg-blue-500 dark:bg-blue-400',
        'amber' => 'bg-amber-400 dark:bg-amber-300',
        'red' => 'bg-red-500 dark:bg-red-400',
        default => 'bg-zinc-400 dark:bg-zinc-500',
    };
    $visibleLines = array_slice($lines, 0, 3);
    $hiddenLines = array_slice($lines, 3);
    $hasMoreItems = $hiddenLines !== [];
@endphp

<article
    class="overflow-hidden rounded-2xl border border-zinc-200/90 bg-white shadow-sm transition hover:border-zinc-300 hover:shadow-md dark:border-zinc-700/80 dark:bg-zinc-900 dark:hover:border-zinc-600"
    data-test="order-card"
>
    <div class="h-1 w-full bg-zinc-100 dark:bg-zinc-800" aria-hidden="true">
        <div
            class="h-1 rounded-e-sm {{ $progressTint }} transition-all duration-300"
            style="width: {{ $progressWidth }}%"
        ></div>
    </div>

    <header class="flex flex-col gap-4 border-b border-zinc-100 p-5 dark:border-zinc-800 sm:flex-row sm:items-start sm:justify-between sm:gap-6">
        <div class="min-w-0 shrink-0">
            <p class="text-2xl font-bold tabular-nums tracking-tight text-zinc-900 dark:text-zinc-100" dir="ltr">
                {{ $formattedTotal }}
            </p>
        </div>
        <div class="flex min-w-0 flex-1 flex-col items-start gap-2 sm:items-end sm:text-end">
            <div class="flex flex-wrap items-center justify-end gap-2">
                <flux:badge color="{{ $status['color'] }}" class="text-xs font-semibold">
                    {{ $status['label'] }}
                </flux:badge>
                @if (is_array($refundSummary) && ($refundSummary['kind'] ?? 'badge') === 'action' && isset($refundSummary['orderId'], $refundSummary['label']))
                    <flux:button
                        type="button"
                        variant="primary"
                        size="sm"
                        wire:click.stop="requestRefundForOrder({{ (int) $refundSummary['orderId'] }})"
                        wire:loading.attr="disabled"
                        wire:target="requestRefundForOrder"
                        class="!bg-accent !text-accent-foreground hover:!bg-accent-hover"
                        data-test="order-card-request-refund"
                    >
                        {{ $refundSummary['label'] }}
                    </flux:button>
                @elseif (is_array($refundSummary) && isset($refundSummary['label'], $refundSummary['color']))
                    <flux:badge color="{{ $refundSummary['color'] }}" class="text-xs font-semibold">
                        {{ $refundSummary['label'] }}
                    </flux:badge>
                @endif
            </div>
            <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">
                {{ $orderNumber }}
            </div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                {{ $formattedDate }}
            </p>
        </div>
    </header>

    <div
        class="p-5"
        x-data="{ showMoreItems: {{ $hasMoreItems ? 'false' : 'true' }} }"
    >
        <div class="space-y-5">
            @foreach ($visibleLines as $line)
                @include('components.partials.order-card-line', ['line' => $line, 'showPrices' => $showPrices, 'borderTop' => false])
            @endforeach

            @if ($hasMoreItems)
                <div x-show="showMoreItems" x-transition class="space-y-5">
                    @foreach ($hiddenLines as $line)
                        @include('components.partials.order-card-line', ['line' => $line, 'showPrices' => $showPrices, 'borderTop' => true])
                    @endforeach
                </div>

                <button
                    type="button"
                    class="w-full rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800/80 dark:text-zinc-200 dark:hover:bg-zinc-800"
                    @click.stop="showMoreItems = !showMoreItems"
                    data-test="order-card-toggle-more"
                >
                    <span x-show="!showMoreItems">{{ __('messages.orders_card_show_more', ['count' => count($hiddenLines)]) }}</span>
                    <span x-show="showMoreItems" x-cloak>{{ __('messages.orders_card_show_less') }}</span>
                </button>
            @endif
        </div>
    </div>

    <footer class="flex flex-col gap-3 border-t border-zinc-100 p-5 dark:border-zinc-800 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('messages.orders_card_summary', ['lines' => $summary['lines'], 'units' => $summary['units']]) }}
        </p>
        <flux:button
            variant="primary"
            icon:trailing="chevron-right"
            :href="$href"
            wire:navigate
            class="w-full shrink-0 !bg-accent !text-accent-foreground hover:!bg-accent-hover sm:w-auto rtl:[&_[data-slot=icon]]:rotate-180"
            data-test="order-card-cta"
        >
            {{ __('messages.view_order') }}
        </flux:button>
    </footer>
</article>
