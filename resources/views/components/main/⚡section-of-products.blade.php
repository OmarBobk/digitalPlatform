<?php

declare(strict_types=1);

use Livewire\Component;

new class extends Component
{
    public array $products;

    public function mount(): void
    {
        $this->products = [
            [
                'id' => 1,
                'name' => 'Kablosuz Kulaklık',
                'price' => 1299,
                'href' => '#',
                'image' => asset('images/promotions/promo-placeholder.svg'),
            ],
            [
                'id' => 2,
                'name' => 'Gaming Mouse',
                'price' => 899,
                'href' => '#',
                'image' => asset('images/promotions/promo-placeholder.svg'),
            ],
            [
                'id' => 3,
                'name' => 'Mekanik Klavye',
                'price' => 1749,
                'href' => '#',
                'image' => asset('images/promotions/promo-placeholder.svg'),
            ],
            [
                'id' => 4,
                'name' => 'USB-C Kablo',
                'price' => 199,
                'href' => '#',
                'image' => asset('images/promotions/promo-placeholder.svg'),
            ],
            [
                'id' => 5,
                'name' => 'Powerbank 20.000mAh',
                'price' => 1099,
                'href' => '#',
                'image' => asset('images/promotions/promo-placeholder.svg'),
            ],
            [
                'id' => 6,
                'name' => 'Bluetooth Hoparlör',
                'price' => 1499,
                'href' => '#',
                'image' => asset('images/promotions/promo-placeholder.svg'),
            ],
            [
                'id' => 7,
                'name' => 'Telefon Kılıfı',
                'price' => 249,
                'href' => '#',
                'image' => asset('images/promotions/promo-placeholder.svg'),
            ],
            [
                'id' => 8,
                'name' => 'Hızlı Şarj Adaptörü',
                'price' => 349,
                'href' => '#',
                'image' => asset('images/promotions/promo-placeholder.svg'),
            ],
        ];
    }
};
?>

<div class="px-2 py-3 sm:px-0 sm:py-4" x-data>
    <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 sm:p-6">
        <div class="flex flex-col gap-4 sm:gap-5">
            <flux:heading size="lg" class="text-start text-zinc-900 dark:text-zinc-100">
                {{ __('main.featured_products') }}
            </flux:heading>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4 lg:grid-cols-4">
                @foreach ($products as $product)
                    <div
                        x-data="{ product: @js($product) }"
                        class="group flex h-full flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white text-zinc-900 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-accent hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:hover:border-accent"
                    >
                        <a
                            href="{{ $product['href'] }}"
                            class="block focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent)"
                            aria-label="{{ $product['name'] }}"
                        >
                            <div class="aspect-[4/3] w-full overflow-hidden bg-zinc-100 dark:bg-zinc-900">
                                <img
                                    src="{{ $product['image'] }}"
                                    alt="{{ $product['name'] }}"
                                    class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]"
                                    width="320"
                                    height="240"
                                    loading="lazy"
                                    decoding="async"
                                />
                            </div>
                        </a>
                        <div class="flex flex-1 flex-col gap-2 px-3 pb-3 pt-2">
                            <a
                                href="{{ $product['href'] }}"
                                class="text-start text-sm font-semibold text-zinc-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent) dark:text-zinc-100"
                            >
                                {{ $product['name'] }}
                            </a>
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-semibold text-(--color-accent)" dir="ltr">
                                    ₺{{ number_format($product['price'], 0, ',', '.') }}
                                </span>
                                <flux:button
                                    type="button"
                                    variant="ghost"
                                    icon="shopping-cart"
                                    class="!h-8 !w-8 !p-0 [&>div>svg]:size-4 !text-zinc-700 dark:!text-zinc-300
                                    hover:!bg-zinc-100 dark:hover:!bg-zinc-700/60 rounded-md"
                                    x-on:click="$store.cart.add(product)"
                                    data-test="cart-add"
                                    aria-label="{{ __('main.add_to_cart_for', ['name' => $product['name']]) }}"
                                />
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>