<x-layouts::auth>
    <div class="flex flex-col gap-7">
        <x-auth-header :title="__('messages.auth_reset_password_title')" :description="__('messages.auth_reset_password_description')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form
            method="POST"
            action="{{ route('password.update') }}"
            class="flex flex-col gap-6"
            x-data="{ submitting: false }"
            x-on:submit="submitting = true"
        >
            @csrf

            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <flux:field>
                <flux:label>{{ __('messages.email_address') }}</flux:label>
                <flux:input
                    name="email"
                    value="{{ request('email') }}"
                    type="email"
                    required
                    autocomplete="email"
                    :placeholder="__('messages.email_placeholder')"
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
                />
                <flux:error name="password_confirmation" />
            </flux:field>

            <flux:button
                type="submit"
                variant="primary"
                class="w-full font-semibold shadow-sm shadow-zinc-900/10 dark:shadow-black/30"
                data-test="reset-password-button"
                x-bind:disabled="submitting"
                x-bind:aria-busy="submitting"
            >
                {{ __('messages.auth_save_new_password') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
