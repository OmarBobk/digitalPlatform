<x-layouts::auth>
    <div class="flex flex-col gap-7">
        <x-auth-header :title="__('messages.auth_forgot_password_title')" :description="__('messages.auth_forgot_password_description')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form
            method="POST"
            action="{{ route('password.email') }}"
            class="flex flex-col gap-6"
            x-data="{ submitting: false }"
            x-on:submit="submitting = true"
        >
            @csrf

            <flux:field>
                <flux:label>{{ __('messages.email_address') }}</flux:label>
                <flux:input
                    name="email"
                    :value="old('email')"
                    type="email"
                    required
                    autofocus
                    autocomplete="email"
                    :placeholder="__('messages.email_placeholder')"
                />
                <flux:error name="email" />
            </flux:field>

            <flux:button
                variant="primary"
                type="submit"
                class="w-full font-semibold shadow-sm shadow-zinc-900/10 dark:shadow-black/30"
                data-test="email-password-reset-link-button"
                x-bind:disabled="submitting"
                x-bind:aria-busy="submitting"
            >
                {{ __('messages.auth_send_reset_link') }}
            </flux:button>
        </form>

        <div class="flex flex-wrap items-center justify-center gap-1 border-t border-zinc-200/80 pt-6 text-center text-sm text-zinc-600 dark:border-zinc-700/80 dark:text-zinc-400">
            <flux:link :href="route('login')" wire:navigate class="font-semibold text-accent hover:underline">
                {{ __('messages.auth_back_to_log_in') }}
            </flux:link>
        </div>
    </div>
</x-layouts::auth>
