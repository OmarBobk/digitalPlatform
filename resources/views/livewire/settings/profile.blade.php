<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('messages.profile_settings') }}</flux:heading>

    <x-settings.layout :heading="__('messages.profile')" :subheading="__('messages.update_your_profile_information')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('messages.name')" type="text" required autofocus autocomplete="name" />

            <flux:input wire:model="username" :label="__('messages.username')" type="text" required autocomplete="username" />

            <div>
                <flux:input wire:model="email" :label="__('messages.email')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('messages.your_email_address_is_unverified') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('messages.click_here_to_re_send_the_verification_email') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('messages.a_new_verification_link_has_been_sent_to_your_email_address') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full">{{ __('messages.save') }}</flux:button>
                </div>

                <x-action-message class="me-3" on="profile-updated">
                    {{ __('messages.saved') }}
                </x-action-message>
            </div>
        </form>

        @if ($this->loyaltyCurrentTierConfig !== null)
            <div class="my-6">
                <x-loyalty.tier-card
                    :current-tier-name="auth()->user()?->loyalty_tier?->value ?? 'bronze'"
                    :discount-percent="(float) $this->loyaltyCurrentTierConfig->discount_percentage"
                    :rolling-spend="$this->loyaltyRollingSpend"
                    :next-tier-name="$this->loyaltyNextTier?->name"
                    :next-tier-min-spend="$this->loyaltyNextTier ? (float) $this->loyaltyNextTier->min_spend : null"
                    :amount-to-next="$this->loyaltyAmountToNextTier"
                    :progress-percent="$this->loyaltyProgressPercent"
                    :window-days="\App\Models\LoyaltySetting::getRollingWindowDays()"
                    layout="full"
                />
            </div>
        @endif

        @if ($this->showDeleteUser)
            <livewire:settings.delete-user-form />
        @endif
    </x-settings.layout>
</section>
