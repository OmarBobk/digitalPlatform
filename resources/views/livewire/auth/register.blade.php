<x-layouts::auth :title="__('messages.' . str_replace(' ', '_', strtolower(config('app.name'))))">
    <div class="flex flex-col gap-6">
        <x-auth-header title="{{ __('messages.create_an_account') }}" description="{{ __('messages.enter_your_details_below_to_create_your_account') }}" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6" id="register-form">
            @csrf

            <input type="hidden" name="timezone_detected" id="timezone-detected" value="">

            <div class="grid gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('messages.name') }}</flux:label>
                    <flux:input
                        name="name"
                        :value="old('name')"
                        type="text"
                        required
                        autofocus
                        autocomplete="name"
                        :placeholder="__('messages.full_name')"
                        class="w-full"
                        class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                    />
                    <flux:error name="name" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('messages.username') }}</flux:label>
                    <flux:input.group>
                        <flux:input.group.prefix>@</flux:input.group.prefix>
                        <flux:input
                            name="username"
                            :value="old('username')"
                            type="text"
                            required
                            autocomplete="username"
                            :placeholder="__('messages.username')"
                            class="w-full"
                            class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                        />
                    </flux:input.group>
                    <flux:error name="username" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('messages.email_address') }}</flux:label>
                    <flux:input
                        name="email"
                        :value="old('email')"
                        type="email"
                        required
                        autocomplete="email"
                        :placeholder="__('messages.email_placeholder')"
                        class="w-full"
                        class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                    />
                    <flux:error name="email" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('messages.password') }}</flux:label>
                    <flux:input
                        name="password"
                        type="password"
                        required
                        autocomplete="new-password"
                        :placeholder="__('messages.password')"
                        viewable
                        class="w-full"
                        class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                    />
                    <flux:error name="password" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('messages.confirm_password') }}</flux:label>
                    <flux:input
                        name="password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        :placeholder="__('messages.confirm_password')"
                        viewable
                        class="w-full"
                        class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                    />
                    <flux:error name="password_confirmation" />
                </flux:field>
                <flux:field class="md:col-span-2">
                    <flux:label>{{ __('messages.phone_number') }}</flux:label>
                    <flux:input.group>
                        <flux:select
                            name="country_code"
                            :value="old('country_code')"
                            autocomplete="tel-country-code"
                            class="max-w-fit focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                        >
                            <flux:select.option value="">{{ __('messages.select_country_code') }}</flux:select.option>
                            <flux:select.option value="+90">+90</flux:select.option>
                            <flux:select.option value="+963">+963</flux:select.option>
                        </flux:select>
                        <flux:input
                            name="phone"
                            :value="old('phone')"
                            type="text"
                            autocomplete="tel"
                            mask="(999) 999-9999"
                            placeholder="( ___ ) ___-____"
                            class="w-full phone-input-rtl"
                            class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                        />
                    </flux:input.group>
                    <flux:error name="phone" />
                </flux:field>
            </div>

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
