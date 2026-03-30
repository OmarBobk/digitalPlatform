<?php

use App\Actions\Cart\RepriceCustomAmountLinePrice;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    #[On('cart-reprice-after-quick-add')]
    public function repriceAfterQuickAdd(int $productId, int $requestedAmount): void
    {
        $this->repriceCustomAmount($productId, $requestedAmount);
    }

    public function repriceCustomAmount(int $productId, mixed $requestedAmount): void
    {
        $identity = (string) (auth()->id() ?? request()->ip() ?? 'guest');
        $result = app(RepriceCustomAmountLinePrice::class)->handle(
            $productId,
            $requestedAmount,
            auth()->user(),
            $identity,
        );

        if (($result['silent'] ?? false) === true) {
            return;
        }

        if (! $result['ok']) {
            $this->dispatch('cart-custom-amount-priced', productId: $productId, message: $result['message']);

            return;
        }

        $this->dispatch(
            'cart-custom-amount-priced',
            productId: $productId,
            price: $result['price'],
            requestedAmount: $result['requested_amount'],
            message: null,
        );
    }
};
?>

@php
    $dropdownCartLocaleTag = str_replace('_', '-', app()->getLocale());
    $dropdownCartAmountMaskEn = str_starts_with($dropdownCartLocaleTag, 'en');
    $dropdownCartMaskDec = $dropdownCartAmountMaskEn ? '.' : ',';
    $dropdownCartMaskThousands = $dropdownCartAmountMaskEn ? ',' : '.';
@endphp


<flux:dropdown position="bottom" align="end" x-data x-init="$store.cart.init()" data-test="cart-dropdown">
        <div class="relative">
            <flux:button
                variant="ghost"
                icon="shopping-cart"
                class="!h-10 !w-10 !p-0 [&>div>svg]:size-5 !text-zinc-700 dark:!text-zinc-300
            hover:!bg-zinc-200 dark:hover:!bg-zinc-800 hover:cursor-pointer rounded-full transition-colors
            focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-(--color-accent)/40 focus-visible:ring-offset-2
            focus-visible:ring-offset-white dark:focus-visible:ring-offset-zinc-900"
                aria-label="{{ __('main.cart_aria_label') }}"
                data-test="cart-button"
            />
            <span
                x-cloak
                x-show="$store.cart.count > 0"
                class="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-(--color-accent) px-1 text-xs font-semibold text-(--color-accent-foreground) shadow-sm"
                x-text="$store.cart.count"
            ></span>
        </div>

        <flux:menu
            keep-open
            x-on:click.stop
            class="min-w-72 w-80 !p-0 rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
        >
            <div class="p-2">
                <div class="flex items-center justify-between px-2 py-1">
                    <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('main.my_cart') }}</span>
                    <button
                        type="button"
                        class="text-xs font-medium text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200"
                        x-cloak
                        x-show="$store.cart.count > 0"
                        x-on:click="$store.cart.clear()"
                    >
                        {{ __('main.clear') }}
                    </button>
                </div>

                <div class="mt-1 max-h-72 overflow-auto divide-y divide-zinc-100 dark:divide-zinc-800">
                    <template x-if="$store.cart.items.length === 0">
                        <div class="px-3 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('main.cart_empty') }}
                        </div>
                    </template>

                    <template x-for="item in $store.cart.items" :key="item.id">
                        <div class="flex gap-3 px-3 py-3">
                            <div class="h-12 w-12 overflow-hidden rounded-lg border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800">
                                <img
                                    :src="item.image"
                                    :alt="item.name"
                                    class="h-full w-full object-cover"
                                    loading="lazy"
                                />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <a
                                        :href="item.href || '#'"
                                        class="truncate text-sm font-semibold text-zinc-900 hover:underline dark:text-zinc-100"
                                        x-text="item.name"
                                    ></a>
                                    <button
                                        type="button"
                                        class="rounded-md p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                                        x-on:click.stop="$store.cart.remove(item.id)"
                                        aria-label="{{ __('main.remove_item') }}"
                                    >
                                        <flux:icon icon="x-mark" class="size-4" />
                                    </button>
                                </div>
                                <div class="mt-2 flex items-start justify-between gap-2">
                                    <template x-if="item.amount_mode === 'custom'">
                                        <div class="min-w-0 flex-1 flex flex-col gap-1">
                                            <input
                                                type="text"
                                                inputmode="numeric"
                                                dir="ltr"
                                                class="w-full rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs font-semibold text-zinc-700 tabular-nums shadow-sm outline-none transition focus:border-(--color-accent) dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200"
                                                x-mask:dynamic="$money($input, '{{ $dropdownCartMaskDec }}', '{{ $dropdownCartMaskThousands }}', 0)"
                                                x-model="item.requested_amount_input"
                                                x-on:blur="
                                                    const ok = $store.cart.updateRequestedAmount(item.id, item.requested_amount_input);
                                                    if (ok) {
                                                        $wire.repriceCustomAmount(item.id, item.requested_amount);
                                                    }
                                                "
                                            />
                                            <div class="text-[10px] leading-tight text-zinc-500 dark:text-zinc-400" dir="ltr">
                                                <span
                                                    x-text="`${item.custom_amount_min ? Number(item.custom_amount_min).toLocaleString() : '-'} – ${item.custom_amount_max ? Number(item.custom_amount_max).toLocaleString() : '-'} · ${item.custom_amount_step ?? 1}`"
                                                ></span>
                                                <span class="ms-1" x-text="item.amount_unit_label ?? ''"></span>
                                            </div>
                                            <div
                                                class="text-[10px] text-red-600 dark:text-red-400"
                                                x-show="$store.cart.getCustomAmountError(item)"
                                                x-text="$store.cart.getCustomAmountError(item)"
                                            ></div>
                                        </div>
                                    </template>
                                    <template x-if="item.amount_mode !== 'custom'">
                                        <div class="inline-flex items-center gap-1 rounded-lg border border-zinc-200 bg-white px-1 py-0.5 text-xs font-semibold text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                                            <button
                                                type="button"
                                                class="size-6 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800 p-[.35rem]"
                                                x-on:click.stop="$store.cart.decrement(item.id)"
                                                aria-label="{{ __('main.decrease') }}"
                                            >
                                                <flux:icon icon="minus" class="size-3" />
                                            </button>
                                            <span class="min-w-5 text-center" x-text="item.quantity"></span>
                                            <button
                                                type="button"
                                                class="size-6 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800 p-[.35rem]"
                                                x-on:click.stop="$store.cart.increment(item.id)"
                                                aria-label="{{ __('main.increase') }}"
                                            >
                                                <flux:icon icon="plus" class="size-3" />
                                            </button>
                                        </div>
                                    </template>
                                    @if(\App\Models\WebsiteSetting::getPricesVisible())
                                    <div class="flex shrink-0 flex-col items-end gap-0.5 text-end">
                                        <span class="text-sm font-semibold text-(--color-accent)" dir="ltr" x-text="$store.cart.format($store.cart.lineTotalForItem(item))"></span>
                                    </div>
                                    @else
                                    <span class="shrink-0 text-sm font-semibold text-zinc-500 dark:text-zinc-400">—</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="mt-2 border-t border-zinc-100 px-3 pt-3 dark:border-zinc-800" x-show="$store.cart.items.length != 0">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-zinc-600 dark:text-zinc-300">{{ __('main.subtotal') }}</span>
                        @if(\App\Models\WebsiteSetting::getPricesVisible())
                        <span class="font-semibold text-zinc-900 dark:text-zinc-100" x-text="$store.cart.format($store.cart.subtotal)"></span>
                        @else
                        <span class="font-semibold text-zinc-500 dark:text-zinc-400">—</span>
                        @endif
                    </div>
                    <div class="mt-3">
                        <a
                            href="{{ route('cart') }}"
                            wire:navigate
                            class="inline-flex w-full items-center justify-center rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
                            data-test="cart-go-to"
                        >
                            {{ __('main.go_to_cart') }}
                        </a>
                    </div>
                </div>
            </div>
        </flux:menu>
    </flux:dropdown>
