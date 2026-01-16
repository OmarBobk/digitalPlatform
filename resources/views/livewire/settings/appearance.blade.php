<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('messages.appearance_settings') }}</flux:heading>

    <x-settings.layout :heading="__('messages.appearance')" :subheading="__('messages.update_the_appearance_settings_for_your_account')">
        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
            <flux:radio value="light" icon="sun">{{ __('messages.light') }}</flux:radio>
            <flux:radio value="dark" icon="moon">{{ __('messages.dark') }}</flux:radio>
            <flux:radio value="system" icon="computer-desktop">{{ __('messages.system') }}</flux:radio>
        </flux:radio.group>
    </x-settings.layout>
</section>
