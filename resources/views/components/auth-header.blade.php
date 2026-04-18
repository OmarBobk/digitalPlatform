@props([
    'title',
    'description',
])

<div {{ $attributes->class(['flex w-full flex-col gap-3 text-center sm:gap-3.5']) }}>
    <div class="mx-auto h-1 w-11 shrink-0 rounded-full bg-accent shadow-[0_0_20px_color-mix(in_oklab,var(--color-accent)_45%,transparent)]" aria-hidden="true"></div>
    <flux:heading size="xl" class="text-balance font-[family-name:'Space_Grotesk',ui-sans-serif,sans-serif] tracking-tight text-zinc-900 dark:text-zinc-50">
        {{ $title }}
    </flux:heading>
    <flux:subheading class="text-balance text-sm leading-relaxed text-zinc-600 sm:text-base dark:text-zinc-400">
        {{ $description }}
    </flux:subheading>
</div>
