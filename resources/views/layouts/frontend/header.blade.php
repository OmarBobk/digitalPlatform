<!DOCTYPE html>
@php
    $isRtl = app()->isLocale('ar');
    $direction = $isRtl ? 'rtl' : 'ltr';
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $direction }}" class="dark">
    <head>
        @include('partials.frontend.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-900">
        <flux:header
            sticky class="!block !px-3 !py-3 border-b border-zinc-200  dark:border-zinc-700 dark:!bg-zinc-900 "
            x-data="{ isScrolled: false }"
            x-init="window.addEventListener('scroll', () => { isScrolled = window.scrollY > 10;})"
            x-bind:class="isScrolled
            ? 'fixed top-0 start-0 end-0 z-50 transition-all bg-white duration-300 shadow-lg border-b border-gray-200'
            : 'fixed top-0 start-0 end-0 z-50 transition-all bg-white duration-300'"
        >
            <div class="mx-auto w-full h-full [:where(&)]:max-w-7xl  items-center">


                <div class="flex flex-wrap sm:flex-nowrap justify-between gap-2 sm:gap-4 items-center w-full mb-3 sm:mb-0">
                    <!-- Logo -->
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center shrink-0 order-1">
                        <span class="text-3xl font-bold leading-none">
                            <span class="text-(--color-accent)">indirim</span><span class="text-(--color-zinc-900) dark:text-(--color-zinc-100)">Go</span>
                        </span>
                    </a>

                    <!-- Search Bar -->
                    <div class=" w-full max-w-3xl mx-auto sm:order-2 order-3">
                        <flux:input
                            placeholder="{{__('main.search_for_games_and_products_...')}}"
                            icon-leading="magnifying-glass"
                            class:input=" focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                        />
                    </div>

                    <!-- Action Icons -->
                    <div class="flex items-center sm:gap-2 shrink-0 sm:order-3 order-2">
                        <!-- Wishlist Icon -->
{{--                        <flux:button--}}
{{--                            variant="ghost"--}}
{{--                            icon="heart"--}}
{{--                            class="!h-10 !w-10 !p-0 [&>div>svg]:size-5 !text-zinc-700 dark:!text-zinc-300--}}
{{--                            hover:!bg-zinc-200 hover:cursor-pointer dark:hover:!bg-zinc-800 rounded-full"--}}
{{--                            aria-label="Favoriler"--}}
{{--                        />--}}

                        <!-- Shopping Cart Icon with Badge -->
                        <livewire:cart.dropdown />

                        <!-- User Profile Icon -->
                        <flux:dropdown position="bottom" align="end">
                            <flux:button
                                variant="ghost"
                                icon="user"
                                class="!h-10 !w-10 !p-0 [&>div>svg]:size-5 !text-zinc-700 dark:!text-zinc-300
                                hover:cursor-pointer hover:!bg-zinc-200 dark:hover:!bg-zinc-800 rounded-full transition-colors
                                focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-(--color-accent)/40 focus-visible:ring-offset-2
                                focus-visible:ring-offset-white dark:focus-visible:ring-offset-zinc-900"
                                aria-label="Kullanıcı"
                            />
                            <flux:navmenu class="min-w-48 rounded-xl border border-zinc-200 bg-white p-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                                @auth
                                    @role('admin|supervisor')
                                        <flux:navmenu.item
                                            icon="home"
                                            href="{{ route('dashboard') }}"
                                            class="rounded-lg !text-zinc-700 hover:!bg-zinc-100 focus-visible:!bg-zinc-100 dark:!text-zinc-200 dark:hover:!bg-zinc-800 dark:focus-visible:!bg-zinc-800"
                                        >
                                            {{ __('main.dashboard') }}
                                        </flux:navmenu.item>
                                    @endrole
                                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                                        @csrf
                                        <flux:menu.item
                                            as="button"
                                            type="submit"
                                            icon="{{ $isRtl ? 'arrow-left-start-on-rectangle' : 'arrow-right-start-on-rectangle' }}"
                                            class="rounded-lg !text-zinc-700 hover:!bg-zinc-100 focus-visible:!bg-zinc-100 dark:!text-zinc-200 dark:hover:!bg-zinc-800 dark:focus-visible:!bg-zinc-800"
                                            data-test="logout-button"
                                        >
                                            {{ __('main.logout') }}
                                        </flux:menu.item>
                                    </form>
                                @else
                                    <flux:navmenu.item
                                        icon="user"
                                        href="{{ route('login') }}"
                                        class="rounded-lg !text-zinc-700 hover:!bg-zinc-100 focus-visible:!bg-zinc-100 dark:!text-zinc-200 dark:hover:!bg-zinc-800 dark:focus-visible:!bg-zinc-800"
                                    >
                                        {{ __('main.login') }}
                                    </flux:navmenu.item>

                                    @if (Route::has('register'))
                                        <flux:navmenu.item
                                            icon="plus"
                                            href="{{ route('register') }}"
                                            class="rounded-lg !text-zinc-700 hover:!bg-zinc-100 focus-visible:!bg-zinc-100 dark:!text-zinc-200 dark:hover:!bg-zinc-800 dark:focus-visible:!bg-zinc-800"
                                        >
                                            {{ __('main.register') }}
                                        </flux:navmenu.item>
                                    @endif
                                @endauth
                            </flux:navmenu>
                        </flux:dropdown>
                    </div>
                </div>
                <flux:separator class="my-3 sm:block hidden" />
                <nav
                    x-data="categoryNav()"
                    x-init="init()"
                    class=" border-zinc-200 dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <div class="mx-auto max-w-7xl ">
                        <div class="relative">
                            <!-- Left button (desktop only) -->
                            <button
                                type="button"
                                class="cursor-pointer hidden lg:flex absolute start-0 top-1/2 -translate-y-1/2 z-20 h-9 w-9 items-center justify-center rounded-full border border-zinc-200 bg-white dark:bg-zinc-900 dark:border-zinc-700 shadow-sm hover:bg-zinc-50 dark:hover:bg-zinc-800 disabled:opacity-30 disabled:cursor-not-allowed"
                                x-on:click="scrollByLogical(-320)"
                                x-bind:disabled="atStart"
                                aria-label="Scroll previous"
                            >
                                <flux:icon icon="chevron-left" class="size-5 text-zinc-700 dark:text-zinc-300 rtl:rotate-180" />
                            </button>

                            <!-- Right button (desktop only) -->
                            <button
                                type="button"
                                class="cursor-pointer hidden lg:flex absolute end-0 top-1/2 -translate-y-1/2 z-20 h-9 w-9 items-center justify-center rounded-full border border-zinc-200 bg-white dark:bg-zinc-900 dark:border-zinc-700 shadow-sm hover:bg-zinc-50 dark:hover:bg-zinc-800 disabled:opacity-30 disabled:cursor-not-allowed"
                                x-on:click="scrollByLogical(320)"
                                x-bind:disabled="atEnd"
                                aria-label="Scroll next"
                            >
                                <flux:icon icon="chevron-right" class="size-5 text-zinc-700 dark:text-zinc-300 rtl:rotate-180" />
                            </button>

                            <!-- Scroll container -->
                            <div
                                x-ref="scroller"
                                x-on:scroll="update()"
                                class="overflow-x-auto scrollbar-hide sm:mx-12"
                            >
                                <!-- Add side padding on desktop so arrows don't overlap items -->
                                <flux:navbar class="gap-4 !py-0 ltr:lg:pr-12 rtl:lg:pl-12">
                                    <flux:navbar.item class="border !border-accent !bg-accent hover:!bg-accent-hover !text-accent-foreground" href="#">Home</flux:navbar.item>
                                    <flux:navbar.item class="border !border-accent" href="#" badge="12">Inbox</flux:navbar.item>
                                    <flux:navbar.item class="border !border-accent" href="#">Contacts</flux:navbar.item>
                                    <flux:navbar.item class="border !border-accent" href="#">Contacts</flux:navbar.item>
                                    <flux:navbar.item class="border !border-accent" href="#">Contacts</flux:navbar.item>
                                    <flux:navbar.item class="border !border-accent" href="#">Contacts</flux:navbar.item>
                                    <flux:navbar.item class="border !border-accent" href="#">Contacts</flux:navbar.item>
                                    <flux:navbar.item class="border !border-accent" href="#">Contacts</flux:navbar.item>
                                    <flux:navbar.item class="border !border-accent" href="#">Contacts</flux:navbar.item>
                                    <flux:navbar.item class="border !border-accent" href="#">Contacts</flux:navbar.item>
                                    <flux:navbar.item class="border !border-accent" href="#">Contacts</flux:navbar.item>
                                    <flux:navbar.item class="border !border-accent" href="#">Contacts</flux:navbar.item>
                                    <flux:navbar.item class="border !border-accent" href="#">Contacts</flux:navbar.item>
                                    <flux:navbar.item class="border !border-accent" href="#">Contacts</flux:navbar.item>
                                    <flux:navbar.item class="border !border-accent" href="#" badge="Pro" badge:color="lime">Calendar</flux:navbar.item>

                                    <flux:dropdown class="border !border-accent rounded-lg">
                                        <flux:navbar.item icon:trailing="chevron-down" class="!border-accent">Account</flux:navbar.item>
                                        <flux:navmenu class="!border-accent">
                                            <flux:navmenu.item href="#">Profile</flux:navmenu.item>
                                            <flux:navmenu.item href="#">Settings</flux:navmenu.item>
                                            <flux:navmenu.item href="#">Billing</flux:navmenu.item>
                                        </flux:navmenu>
                                    </flux:dropdown>
                                </flux:navbar>
                            </div>

                            <!-- Optional fade edges (desktop only) -->
                            <div class="pointer-events-none hidden lg:block absolute inset-y-0 start-0 w-10 ltr:bg-gradient-to-r rtl:bg-gradient-to-l from-white dark:from-zinc-900 to-transparent"></div>
                            <div class="pointer-events-none hidden lg:block absolute inset-y-0 end-[-2rem] w-10 ltr:bg-gradient-to-l rtl:bg-gradient-to-r from-white dark:from-zinc-900 to-transparent"></div>
                        </div>
                    </div>

                    <script>
                        function categoryNav() {
                            return {
                                atStart: true,
                                atEnd: false,
                                isRtl: false,
                                rtlScrollType: 'reverse',

                                init() {
                                    this.isRtl = this.getDirection() === 'rtl';
                                    this.rtlScrollType = this.isRtl ? this.getRtlScrollType() : 'ltr';
                                    this.update();
                                    // keep buttons correct on resize
                                    window.addEventListener('resize', () => this.update());
                                },

                                getDirection() {
                                    const root = this.$root.closest('[dir]');
                                    return root?.getAttribute('dir') ?? document.documentElement.getAttribute('dir') ?? 'ltr';
                                },

                                getRtlScrollType() {
                                    const el = this.$refs.scroller;
                                    if (!el) {
                                        return 'reverse';
                                    }

                                    const initial = el.scrollLeft;
                                    el.scrollLeft = 1;
                                    const after = el.scrollLeft;
                                    el.scrollLeft = initial;

                                    if (after === 0) {
                                        return 'negative';
                                    }

                                    return initial === 0 ? 'reverse' : 'default';
                                },

                                getLogicalScroll() {
                                    const el = this.$refs.scroller;
                                    if (!el) {
                                        return 0;
                                    }

                                    const max = Math.max(el.scrollWidth - el.clientWidth, 0);

                                    if (!this.isRtl) {
                                        return Math.max(0, Math.min(el.scrollLeft, max));
                                    }

                                    const raw = el.scrollLeft;

                                    if (this.rtlScrollType === 'negative') {
                                        return Math.abs(raw);
                                    }

                                    if (this.rtlScrollType === 'default') {
                                        return max - raw;
                                    }

                                    return raw;
                                },

                                scrollByLogical(px) {
                                    if (!this.$refs.scroller) {
                                        return;
                                    }

                                    const direction = this.isRtl && this.rtlScrollType !== 'reverse' ? -1 : 1;
                                    this.$refs.scroller.scrollBy({ left: px * direction, behavior: 'smooth' });
                                    // update after scroll animation starts
                                    setTimeout(() => this.update(), 80);
                                },

                                update() {
                                    const el = this.$refs.scroller;
                                    if (!el) {
                                        return;
                                    }

                                    const max = Math.max(el.scrollWidth - el.clientWidth, 0);
                                    const position = this.getLogicalScroll();
                                    // small tolerance for float rounding
                                    this.atStart = position <= 2;
                                    this.atEnd = position >= (max - 2);
                                }
                            }
                        }

                    </script>
                </nav>

            </div>
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
