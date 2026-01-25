<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900 {{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
        <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div class="bg-muted relative hidden h-full flex-col p-10 text-white lg:flex dark:border-e dark:border-neutral-800">
                <div class="absolute inset-0 bg-neutral-900"></div>
                <!-- Language Switcher - Top Right Corner of Dark Panel -->
                <div class="relative z-20 ms-auto [&_button]:text-white [&_button]:hover:text-white/80 [&_button_.flux-icon]:text-white [&_button_.flux-icon]:hover:text-white/80">
                    <livewire:language-switcher />
                </div>
                <a href="{{ route('home') }}" class="relative z-20 flex items-center text-lg font-medium" wire:navigate>
                    <span class="flex h-10 w-10 items-center justify-center rounded-md">
                        <x-app-logo-icon class="me-2 h-7 fill-current text-white" />
                    </span>
                    {{ config('app.name', 'Laravel') }}
                </a>

                @php
                    [$message, $author] = str(Illuminate\Foundation\Inspiring::quotes()->random())->explode('-');
                @endphp

                <div class="relative z-20 mt-auto">
                    <blockquote class="space-y-2">
                        <flux:heading size="lg">&ldquo;{{ trim($message) }}&rdquo;</flux:heading>
                        <footer><flux:heading>{{ trim($author) }}</flux:heading></footer>
                    </blockquote>
                </div>
            </div>
            <div class="relative w-full lg:p-8">
                <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <!-- Mobile Header with Logo and Language Switcher -->
                    <div class="flex items-start justify-between gap-4 lg:hidden">
                        <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                            <span class="flex h-9 w-9 items-center justify-center rounded-md">
                                <x-app-logo-icon class="size-9 fill-current text-black dark:text-white" />
                            </span>
                            <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                        </a>
                        <div class="mt-1">
                            <livewire:language-switcher />
                        </div>
                    </div>
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
