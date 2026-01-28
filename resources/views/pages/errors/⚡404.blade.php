<?php

use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::frontend')] class extends Component {
    public function render()
    {
        return $this->view()->title(__('messages.not_found'));
    }
};
?>

@php
    $previousUrl = url()->previous();
    $currentUrl = url()->current();
    $blockedPaths = ['/404', '/admin', '/dashboard', '/categories', '/packages', '/products', '/topups'];
    $backUrl = $previousUrl;

    foreach ($blockedPaths as $path) {
        if (str_contains($previousUrl, $path)) {
            $backUrl = route('home');
            break;
        }
    }

    if ($backUrl === $currentUrl) {
        $backUrl = route('home');
    }
@endphp

<div class="mx-auto flex min-h-[calc(100vh-8rem)] max-w-4xl flex-col justify-center px-4 py-12 sm:py-16">
    <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 sm:p-10">
        <div class="flex flex-col gap-6">
            <div class="flex flex-wrap items-center gap-3">
                <flux:badge color="red" class="uppercase tracking-[0.2em]">404</flux:badge>
                <flux:heading size="lg" class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100 sm:text-3xl">
                    {{ __('messages.not_found') }}
                </flux:heading>
            </div>

            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('messages.page_not_found_message') }}
            </flux:text>

            <div class="flex flex-wrap gap-3">
                <flux:button
                    as="a"
                    href="{{ route('home') }}"
                    wire:navigate
                    variant="primary"
                    icon="home"
                    class="!bg-accent !text-accent-foreground hover:!bg-accent-hover"
                >
                    {{ __('messages.homepage') }}
                </flux:button>

                <flux:button
                    as="a"
                    href="{{ $backUrl }}"
                    wire:navigate
                    variant="outline"
                    icon="arrow-left"
                    class="!text-zinc-700 dark:!text-zinc-200"
                >
                    {{ __('messages.back') }}
                </flux:button>
            </div>
        </div>
    </div>
</div>
