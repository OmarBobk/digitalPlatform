<x-layouts::auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('messages.log_in_to_your_account')" :description="__('messages.enter_your_username_and_password_below_to_log_in')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Username -->
            <flux:input
                name="username"
                :label="__('messages.username')"
                :value="old('username')"
                type="text"
                required
                autofocus
                autocomplete="username"
                :placeholder="__('messages.username')"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('messages.password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('messages.password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('messages.forgot_your_password') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('messages.remember_me')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('messages.log_in') }}
                </flux:button>
            </div>
        </form>

        @if (Route::has('register'))
            <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
                <span>{{ __('messages.dont_have_an_account') }}</span>
                <flux:link :href="route('register')" wire:navigate>{{ __('messages.sign_up') }}</flux:link>
            </div>
        @endif
    </div>
</x-layouts::auth>
