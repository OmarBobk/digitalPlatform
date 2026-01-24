<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->title(__('main.dashboard'));
    }
};
?>

<div>
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            </div>
            <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            </div>
            <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            </div>
        </div>
        <div class="relative h-full flex-1 overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            <flux:dropdown>
                <flux:button icon:trailing="chevron-down">{{ __('messages.options') }}</flux:button>

                <flux:menu>
                    <flux:menu.item icon="plus">{{ __('messages.new_post') }}</flux:menu.item>

                    <flux:menu.separator />

                    <flux:menu.submenu :heading="__('messages.sort_by')">
                        <flux:menu.radio.group>
                            <flux:menu.radio checked>{{ __('messages.name') }}</flux:menu.radio>
                            <flux:menu.radio>{{ __('messages.date') }}</flux:menu.radio>
                            <flux:menu.radio>{{ __('messages.popularity') }}</flux:menu.radio>
                        </flux:menu.radio.group>
                    </flux:menu.submenu>

                    <flux:menu.submenu :heading="__('messages.filter')">
                        <flux:menu.checkbox checked>{{ __('messages.draft') }}</flux:menu.checkbox>
                        <flux:menu.checkbox checked>{{ __('messages.published') }}</flux:menu.checkbox>
                        <flux:menu.checkbox>{{ __('messages.archived') }}</flux:menu.checkbox>
                    </flux:menu.submenu>

                    <flux:menu.separator />

                    <flux:menu.item variant="danger" icon="trash">{{ __('messages.delete') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>
</div>
