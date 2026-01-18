<?php

use Livewire\Component;

new class extends Component
{
    public string $currentLocale;

    public function mount(): void
    {
        $this->currentLocale = app()->getLocale();
    }

    public function updatedCurrentLocale(): void
    {
        $this->currentLocale = app()->getLocale();
    }
};
?>

<div>
    <flux:dropdown>
        <flux:button variant="ghost" size="sm" icon="language" class="w-auto justify-center sm:w-full sm:justify-start" icon:trailing="chevron-down">
            <span class="hidden sm:inline">{{ __('messages.language') }}</span>
        </flux:button>

        <flux:menu>
            <flux:menu.radio.group>
                <flux:menu.item
                    :href="route('language.switch', ['locale' => 'en'])"
                    :active="$currentLocale === 'en'"
                    icon="check"
                    :icon-active="$currentLocale === 'en'"
                    wire:navigate
                    class="{{$currentLocale === 'en' ? 'hidden' : ''}}"
                >
                    {{ __('messages.english') }}
                </flux:menu.item>
                <flux:menu.item
                    :href="route('language.switch', ['locale' => 'ar'])"
                    :active="$currentLocale === 'ar'"
                    icon="check"
                    :icon-active="$currentLocale === 'ar'"
                    wire:navigate
                    class="{{$currentLocale === 'ar' ? 'hidden' : ''}}"
                >
                    {{ __('messages.arabic') }}
                </flux:menu.item>
            </flux:menu.radio.group>
        </flux:menu>
    </flux:dropdown>
</div>
