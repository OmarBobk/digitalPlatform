@props(['fallback' => null, 'label' => __('messages.back')])

@php
    $fallbackUrl = $fallback ?? route('home');
    $previousUrl = url()->previous();
    $currentUrl = url()->current();
    $backUrl = $previousUrl && $previousUrl !== $currentUrl ? $previousUrl : $fallbackUrl;
    $backIcon = app()->isLocale('ar') ? 'arrow-right' : 'arrow-left';
@endphp

<flux:button
    as="a"
    href="{{ $backUrl }}"
    wire:navigate
    variant="ghost"
    :icon="$backIcon"
    aria-label="{{ $label }}"
    data-test="back-button"
    {{ $attributes->merge([
        'class' => '!h-10 !w-10 !p-0 rounded-full border border-zinc-200 bg-white text-zinc-700 shadow-sm transition hover:bg-zinc-100 hover:text-zinc-900 focus-visible:ring-2 focus-visible:ring-(--color-accent)/40 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800 dark:focus-visible:ring-offset-zinc-900',
    ]) }}
/>
