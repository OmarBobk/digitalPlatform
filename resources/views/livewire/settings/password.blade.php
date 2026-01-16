<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('messages.password_settings') }}</flux:heading>

    <x-settings.layout :heading="__('messages.update_password')" :subheading="__('messages.ensure_your_account_is_using_a_long_random_password_to_stay_secure')">
        <form method="POST" wire:submit="updatePassword" class="mt-6 space-y-6">
            <flux:input
                wire:model="current_password"
                :label="__('messages.current_password')"
                type="password"
                required
                autocomplete="current-password"
            />
            <flux:input
                wire:model="password"
                :label="__('messages.new_password')"
                type="password"
                required
                autocomplete="new-password"
            />
            <flux:input
                wire:model="password_confirmation"
                :label="__('messages.confirm_password')"
                type="password"
                required
                autocomplete="new-password"
            />

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full">{{ __('messages.save') }}</flux:button>
                </div>

                <x-action-message class="me-3" on="password-updated">
                    {{ __('messages.saved') }}
                </x-action-message>
            </div>
        </form>
    </x-settings.layout>
</section>
