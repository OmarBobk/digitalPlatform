<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-dvh antialiased {{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
        <div class="relative grid min-h-dvh flex-col lg:max-w-none lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] lg:px-0">
            <div class="relative hidden flex-col overflow-hidden bg-neutral-950 px-10 py-12 text-white lg:flex lg:border-e lg:border-white/10">
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_120%_80%_at_20%_-10%,color-mix(in_oklab,var(--color-accent)_28%,transparent),transparent_55%),radial-gradient(ellipse_90%_70%_at_100%_100%,color-mix(in_oklab,var(--color-accent)_12%,transparent),transparent_50%)]"></div>
                <div class="pointer-events-none absolute inset-0 opacity-[0.07] [background-image:linear-gradient(to_right,rgb(255_255_255)_1px,transparent_1px),linear-gradient(to_bottom,rgb(255_255_255)_1px,transparent_1px)] [background-size:32px_32px]"></div>

                <div class="relative z-20 ms-auto [&_button]:text-white [&_button]:hover:text-white/85 [&_button_.flux-icon]:text-white [&_button_.flux-icon]:hover:text-white/85">
                    <livewire:language-switcher />
                </div>

                <a href="{{ route('home') }}" wire:navigate class="relative z-20 mt-4 flex items-center gap-3 text-lg font-semibold tracking-tight transition hover:opacity-90">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15 backdrop-blur-sm">
                        <x-app-logo-icon tone="on-dark" class="size-8" />
                    </span>
                    <span class="font-[family-name:'Space_Grotesk',ui-sans-serif,sans-serif] text-xl text-white">{{ config('app.name', 'Laravel') }}</span>
                </a>

                @php
                    [$message, $author] = str(Illuminate\Foundation\Inspiring::quotes()->random())->explode('-');
                @endphp

                <div class="relative z-20 mt-auto max-w-sm pt-16">
                    <div class="mb-3 h-1 w-10 rounded-full bg-accent" aria-hidden="true"></div>
                    <blockquote class="space-y-3">
                        <p class="font-[family-name:'Space_Grotesk',ui-sans-serif,sans-serif] text-2xl font-medium leading-snug text-white/95">
                            &ldquo;{{ trim($message) }}&rdquo;
                        </p>
                        <footer class="text-sm font-medium text-white/55">{{ trim($author) }}</footer>
                    </blockquote>
                </div>
            </div>

            <div class="relative flex flex-col justify-center bg-linear-to-b from-zinc-50 to-zinc-100/90 px-5 py-10 dark:from-zinc-950 dark:to-zinc-900 sm:px-8 lg:px-12 lg:py-14">
                <div
                    class="pointer-events-none absolute inset-0 opacity-40 dark:opacity-25 [background-image:radial-gradient(circle_at_20%_20%,color-mix(in_oklab,var(--color-accent)_14%,transparent),transparent_45%),radial-gradient(circle_at_90%_10%,color-mix(in_oklab,var(--color-accent)_10%,transparent),transparent_40%)]"
                    aria-hidden="true"
                ></div>

                <div class="relative mx-auto flex w-full max-w-md flex-col gap-8">
                    <div class="flex items-start justify-between gap-4 lg:hidden">
                        <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2.5 rounded-xl font-semibold text-zinc-900 transition hover:opacity-90 dark:text-zinc-100">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-white shadow-sm ring-1 ring-zinc-200/80 dark:bg-zinc-800 dark:ring-zinc-700/80">
                                <x-app-logo-icon tone="on-light" class="size-8" />
                            </span>
                            <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                        </a>
                        <div class="pt-1">
                            <livewire:language-switcher />
                        </div>
                    </div>

                    <div
                        class="rounded-2xl border border-zinc-200/90 bg-white/90 p-7 shadow-[0_1px_0_0_rgb(0_0_0/0.03),0_18px_50px_-24px_rgb(0_0_0/0.18)] ring-1 ring-zinc-900/[0.02] backdrop-blur-md dark:border-zinc-700/80 dark:bg-zinc-900/85 dark:shadow-[0_24px_80px_-32px_rgb(0_0_0/0.55)] dark:ring-white/[0.04] sm:p-8"
                    >
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
        @RegisterServiceWorkerScript
        @fluxScripts
    </body>
</html>
