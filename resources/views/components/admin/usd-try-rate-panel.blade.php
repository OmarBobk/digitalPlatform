@props([
    'variant' => 'sidebar',
])

@role('admin')
    @php
        $rateDisplay = \App\Models\WebsiteSetting::getUsdTryRateAdminDisplay();
        $updated = \App\Models\WebsiteSetting::getUsdTryRateUpdatedAt();
    @endphp
    @if ($variant === 'sidebar')
        <div
            data-test="admin-usd-try-rate-sidebar"
            class="mx-3 mt-1 mb-3 rounded-lg border border-zinc-200 bg-zinc-100/80 px-3 py-2.5 text-xs dark:border-zinc-600 dark:bg-zinc-800/60"
            dir="ltr"
        >
            <div class="font-semibold text-zinc-700 dark:text-zinc-200">{{ __('messages.admin_usd_try_rate_heading') }}</div>
            <div class="mt-1 font-mono text-sm text-zinc-900 dark:text-zinc-50">{{ $rateDisplay ?? '—' }}</div>
            @if ($updated !== null)
                <div class="mt-1 text-zinc-500 dark:text-zinc-400">
                    {{ __('messages.admin_usd_try_rate_updated_at', ['datetime' => $updated->timezone(config('app.timezone'))->format('M j, Y H:i')]) }}
                </div>
            @elseif ($rateDisplay === null)
                <div class="mt-1 text-amber-700 dark:text-amber-400">{{ __('messages.admin_usd_try_rate_missing') }}</div>
            @endif
        </div>
    @else
        <div
            data-test="admin-usd-try-rate-storefront"
            class="me-1 flex max-w-[11rem] shrink-0 flex-col rounded-md border border-amber-200/90 bg-amber-50 px-2 py-1 text-[10px] leading-tight sm:me-2 sm:max-w-none sm:flex-row sm:items-baseline sm:gap-1.5 sm:text-[11px] dark:border-amber-900/50 dark:bg-amber-950/45"
            dir="ltr"
            @if ($updated !== null)
                title="{{ $updated->timezone(config('app.timezone'))->toIso8601String() }}"
            @endif
        >
            <span class="font-semibold text-amber-900 dark:text-amber-100">{{ __('messages.admin_usd_try_rate_short') }}</span>
            <span class="font-mono text-amber-950 dark:text-amber-50">{{ $rateDisplay ?? '—' }}</span>
        </div>
    @endif
@endrole
