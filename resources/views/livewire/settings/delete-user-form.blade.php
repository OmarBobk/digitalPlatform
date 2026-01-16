<section class="mt-10 space-y-6">
    <div class="relative mb-5">
        <flux:heading>{{ __('messages.delete_account') }}</flux:heading>
        <flux:subheading>{{ __('messages.delete_your_account_and_all_of_its_resources') }}</flux:subheading>
    </div>

    <flux:modal.trigger name="confirm-user-deletion">
        <flux:button variant="danger" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
            {{ __('messages.delete_account') }}
        </flux:button>
    </flux:modal.trigger>

    <flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
        <form method="POST" wire:submit="deleteUser" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('messages.are_you_sure_you_want_to_delete_your_account') }}</flux:heading>

                <flux:subheading>
                    {{ __('messages.once_your_account_is_deleted_all_of_its_resources_and_data_will_be_permanently_deleted_please_enter_your_password_to_confirm_you_would_like_to_permanently_delete_your_account') }}
                </flux:subheading>
            </div>

            <flux:input wire:model="password" :label="__('messages.password')" type="password" />

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('messages.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" type="submit">{{ __('messages.delete_account') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
