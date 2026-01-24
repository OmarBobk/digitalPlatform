<?php

use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::frontend')] class extends Component {

    public function render()
    {
        return $this->view()->title(__('main.shopping_cart'));
    }

};
?>

<div
    class="mx-auto w-full max-w-7xl px-3 py-6 sm:px-0 sm:py-10"
    x-data
    x-init="$store.cart.init()"
    data-test="cart-page"
>
    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        <section class="flex-1">
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                        Sepetim
                    </flux:heading>
                    <span
                        class="text-sm text-zinc-500 dark:text-zinc-400"
                        x-text="$store.cart.count + ' ürün'"
                    ></span>
                </div>

                <ul class="mt-4 divide-y divide-zinc-100 dark:divide-zinc-700">
                    <template x-if="$store.cart.items.length === 0">
                        <li class="flex flex-col items-center gap-3 py-10 text-center">
                            <div class="flex size-12 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 dark:bg-zinc-900 dark:text-zinc-300">
                                <flux:icon icon="shopping-cart" class="size-5" />
                            </div>
                            <div class="text-sm text-zinc-600 dark:text-zinc-300">
                                Sepetiniz şu an boş.
                            </div>
                            <a
                                href="{{ route('home') }}"
                                wire:navigate
                                class="inline-flex items-center justify-center rounded-lg border border-zinc-200 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
                            >
                                Alışverişe devam et
                            </a>
                        </li>
                    </template>

                    <template x-for="item in $store.cart.items" :key="item.id">
                        <li class="flex flex-col gap-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-4">
                                <div class="h-16 w-16 overflow-hidden rounded-xl border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900">
                                    <img
                                        :src="item.image"
                                        :alt="item.name"
                                        class="h-full w-full object-cover"
                                        loading="lazy"
                                    />
                                </div>
                                <div class="min-w-0">
                                    <a
                                        :href="item.href || '#'"
                                        class="block truncate text-sm font-semibold text-zinc-900 hover:underline dark:text-zinc-100"
                                        x-text="item.name"
                                    ></a>
                                    <div class="mt-1 text-sm font-semibold text-(--color-accent)" x-text="$store.cart.format(item.price)"></div>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-4 sm:justify-end">
                                <div class="inline-flex items-center gap-1 rounded-lg border border-zinc-200 bg-white px-1 py-0.5 text-xs font-semibold text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                                    <button
                                        type="button"
                                        class="size-7 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                        x-on:click.stop="$store.cart.decrement(item.id)"
                                        aria-label="Azalt"
                                    >
                                        <flux:icon icon="minus" class="size-3" />
                                    </button>
                                    <span class="min-w-6 text-center text-sm" x-text="item.quantity"></span>
                                    <button
                                        type="button"
                                        class="size-7 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                        x-on:click.stop="$store.cart.increment(item.id)"
                                        aria-label="Artır"
                                    >
                                        <flux:icon icon="plus" class="size-3" />
                                    </button>
                                </div>

                                <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100" x-text="$store.cart.format(item.price * item.quantity)"></div>

                                <button
                                    type="button"
                                    class="rounded-md p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                                    x-on:click.stop="$store.cart.remove(item.id)"
                                    aria-label="Ürünü kaldır"
                                >
                                    <flux:icon icon="x-mark" class="size-4" />
                                </button>
                            </div>
                        </li>
                    </template>
                </ul>
            </div>
        </section>

        <aside class="w-full lg:w-80 lg:sticky lg:top-24">
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 sm:p-6">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                    Sipariş Özeti
                </flux:heading>

                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between text-zinc-600 dark:text-zinc-300">
                        <span>Ara toplam</span>
                        <span class="font-semibold text-zinc-900 dark:text-zinc-100" x-text="$store.cart.format($store.cart.subtotal)"></span>
                    </div>
                    <div class="flex items-center justify-between text-zinc-600 dark:text-zinc-300">
                        <span>Kargo</span>
                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">Ücretsiz</span>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-between border-t border-zinc-100 pt-4 text-base font-semibold dark:border-zinc-700">
                    <span class="text-zinc-900 dark:text-zinc-100">Toplam</span>
                    <span class="text-(--color-accent)" x-text="$store.cart.format($store.cart.subtotal)"></span>
                </div>

                <div class="mt-4 space-y-3">
                    <flux:button
                        variant="primary"
                        class="w-full justify-center !bg-accent !text-accent-foreground hover:!bg-accent-hover"
                        x-bind:disabled="$store.cart.count === 0"
                        data-test="cart-checkout"
                    >
                        Ödemeye geç
                    </flux:button>
                    <button
                        type="button"
                        class="w-full rounded-lg border border-zinc-200 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
                        x-bind:disabled="$store.cart.count === 0"
                        x-on:click="$store.cart.clear()"
                        data-test="cart-clear"
                    >
                        Sepeti temizle
                    </button>
                </div>
            </div>
        </aside>
    </div>
</div>
