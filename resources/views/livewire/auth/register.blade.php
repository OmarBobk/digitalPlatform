<x-layouts::auth :title="__('messages.' . str_replace(' ', '_', strtolower(config('app.name'))))">
    <div class="flex flex-col gap-6">
        <x-auth-header title="{{ __('messages.create_an_account') }}" description="{{ __('messages.enter_your_details_below_to_create_your_account') }}" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6" id="register-form">
            @csrf

            <!-- Hidden timezone field -->
            <input type="hidden" name="timezone_detected" id="timezone-detected" value="">

            <!-- Name -->
            <flux:input
                name="name"
                :label="__('messages.name')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('messages.full_name')"
            />

            <!-- Username -->
            <flux:input
                name="username"
                :label="__('messages.username')"
                :value="old('username')"
                type="text"
                required
                autocomplete="username"
                :placeholder="__('messages.username')"
            />

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('messages.email_address')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                :placeholder="__('messages.email_placeholder')"
            />

            <!-- Country Code -->
            <flux:select name="country_code" :label="__('messages.country_code')" :value="old('country_code')" autocomplete="tel-country-code" placeholder="{{ __('messages.select_country_code') }}">
                <flux:select.option value="+963">🇸🇾 {{ __('messages.syria') }} (+963)</flux:select.option>
                <flux:select.option value="+90">🇹🇷 {{ __('messages.turkey') }} (+90)</flux:select.option>
            </flux:select>

            <!-- Phone -->
            <flux:input
                name="phone"
                :label="__('messages.phone_number')"
                :value="old('phone')"
                type="tel"
                autocomplete="tel"
                :placeholder="__('messages.phone_number')"
                class="phone-input-rtl"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('messages.password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('messages.password')"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('messages.confirm_password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('messages.confirm_password')"
                viewable
            />

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('messages.create_account') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('messages.already_have_an_account') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('messages.log_in') }}</flux:link>
        </div>
    </div>

    <script>
        (function() {
            // Detect user's timezone using JavaScript
            const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
            const timezoneInput = document.getElementById('timezone-detected');
            
            if (timezoneInput) {
                timezoneInput.value = timezone;
            }

            // Optional: Also update timezone when country code changes as fallback
            const countryCodeSelect = document.querySelector('select[name="country_code"]');
            const form = document.getElementById('register-form');
            
            if (form && !timezoneInput.value) {
                // If timezone wasn't detected, set it on form submit based on country code
                form.addEventListener('submit', function(e) {
                    if (!timezoneInput.value && countryCodeSelect && countryCodeSelect.value) {
                        // Map country code to timezone as fallback
                        const countryCodeToTimezone = {
                            '+963': 'Asia/Damascus',
                            '+90': 'Europe/Istanbul'
                        };
                        
                        const mappedTimezone = countryCodeToTimezone[countryCodeSelect.value];
                        if (mappedTimezone) {
                            timezoneInput.value = mappedTimezone;
                        }
                    }
                });
            }
        })();
    </script>
</x-layouts::auth>
