<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800 {{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('messages.platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('messages.dashboard') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="tag" :href="route('categories')" :current="request()->routeIs('categories')" wire:navigate>
                        {{ __('messages.categories') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="cube" :href="route('packages')" :current="request()->routeIs('packages')" wire:navigate>
                        {{ __('messages.packages') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="shopping-cart" :href="route('products')" :current="request()->routeIs('products')" wire:navigate>
                        {{ __('messages.products') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="list-bullet" :href="route('fulfillments')" :current="request()->routeIs('fulfillments')" wire:navigate>
                        {{ __('messages.fulfillments') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="shopping-bag" :href="route('admin.orders.index')" :current="request()->routeIs('admin.orders.*')" wire:navigate>
                        {{ __('messages.orders') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="receipt-refund" :href="route('refunds')" :current="request()->routeIs('refunds')" wire:navigate>
                        {{ __('messages.refund_requests') }}
                    </flux:sidebar.item>
                    @if (auth()->user()?->can('manage_topups'))
                        <flux:sidebar.item icon="wallet" :href="route('topups')" :current="request()->routeIs('topups')" wire:navigate>
                            {{ __('messages.topups') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit" target="_blank">
                    {{ __('messages.repository') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="book-open-text" href="{{route('home')}}" target="_blank">
                    {{ __('messages.homepage') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>



            <flux:sidebar.nav>
                <livewire:language-switcher />
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />


            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('messages.settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('messages.log_out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>


        </flux:header>

        {{ $slot }}


        @fluxScripts
    </body>
</html>
