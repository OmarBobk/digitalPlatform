<x-layouts::auth>
    <div class="flex flex-col gap-7">
        <x-auth-header :title="__('messages.log_in_to_your_account')" :description="__('messages.enter_your_username_and_password_below_to_log_in')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form
            method="POST"
            action="{{ route('login.store') }}"
            class="flex flex-col gap-6"
            x-data="{ submitting: false }"
            x-on:submit="submitting = true"
        >
            @csrf

            <flux:field>
                <flux:label>{{ __('messages.username') }}</flux:label>
                <flux:input
                    name="username"
                    :value="old('username')"
                    type="text"
                    required
                    autofocus
                    autocomplete="username"
                    :placeholder="__('messages.username')"
                />
                <flux:error name="username" />
            </flux:field>

            <flux:field>
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <flux:label>{{ __('messages.password') }}</flux:label>
                    @if (Route::has('password.request'))
                        <flux:link class="text-xs font-medium text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200" :href="route('password.request')" wire:navigate>
                            {{ __('messages.forgot_your_password') }}
                        </flux:link>
                    @endif
                </div>
                <flux:input
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('messages.password')"
                    viewable
                />
                <flux:error name="password" />
            </flux:field>

            <flux:checkbox name="remember" :label="__('messages.remember_me')" :checked="old('remember')" />

            <flux:button
                variant="primary"
                type="submit"
                class="w-full font-semibold shadow-sm shadow-zinc-900/10 dark:shadow-black/30"
                data-test="login-button"
                x-bind:disabled="submitting"
                x-bind:aria-busy="submitting"
            >
                {{ __('messages.log_in') }}
            </flux:button>
        </form>

        @if (Route::has('register'))
            <div class="flex flex-wrap items-center justify-center gap-1 border-t border-zinc-200/80 pt-6 text-center text-sm text-zinc-600 dark:border-zinc-700/80 dark:text-zinc-400">
                <span>{{ __('messages.dont_have_an_account') }}</span>
                <flux:link :href="route('register')" wire:navigate class="font-semibold text-accent hover:underline">
                    {{ __('messages.sign_up') }}
                </flux:link>
            </div>
        @endif
    </div>
</x-layouts::auth>
