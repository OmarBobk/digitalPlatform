<div>
    <flux:dropdown >
        <flux:button variant="ghost" size="sm" icon="language" class="w-full justify-start" icon:trailing="chevron-down">
            {{ __('messages.language') }}
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
