<?php

declare(strict_types=1);

use App\Actions\Packages\ResolvePackageRequirements;
use App\Models\Product;
use Livewire\Component;

new class extends Component
{
    public array $products = [];

    public function mount(): void
    {
        $placeholderImage = asset('images/promotions/promo-placeholder.svg');
        $resolver = app(ResolvePackageRequirements::class);

        $this->products = Product::query()
            ->select(['id', 'package_id', 'name', 'slug', 'entry_price', 'retail_price', 'order'])
            ->with([
                'package:id,name,image,is_active',
                'package.requirements:id,package_id,key,label,type,is_required,validation_rules,order',
            ])
            ->where('is_active', true)
            ->whereHas('package', fn ($query) => $query->where('is_active', true))
            ->orderBy('order')
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (Product $product): array => $this->mapProduct($product, $resolver, $placeholderImage))
            ->all();
    }

    private function mapProduct(Product $product, ResolvePackageRequirements $resolver, string $placeholderImage): array
    {
        $resolved = $resolver->handle($product->package?->requirements ?? collect());

        return [
            'id' => $product->id,
            'package_id' => $product->package_id,
            'package_name' => $product->package?->name,
            'name' => $product->name,
            'price' => $product->retail_price,
            'href' => '#',
            'image' => filled($product->package?->image)
                ? asset($product->package->image)
                : $placeholderImage,
            'requirements_schema' => $resolved['schema'],
            'requirements_rules' => $resolved['rules'],
            'requirements_attributes' => $resolved['attributes'],
        ];
    }
};
?>

<div class="px-2 py-3 sm:px-0 sm:py-4">
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
                        <button
                            x-on:click="$dispatch('open-buy-now', { productId: {{ $product['id'] }} })"
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
                        </button>
                        <div class="flex flex-1 flex-col gap-2 px-3 pb-3 pt-2">
                            <button
                                x-on:click="$dispatch('open-buy-now', { productId: {{ $product['id'] }} })"
                                class="text-start text-sm font-semibold text-zinc-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent) dark:text-zinc-100"
                            >
                                {{ $product['name'] }}
                            </button>
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-2">
                                <span
                                    class="shrink-0 tabular-nums text-base font-bold text-(--color-accent)"
                                    dir="ltr"
                                    aria-label="{{ __('messages.amount') }}: ${{ number_format((float) $product['price'], 2) }}"
                                >
                                    ${{ number_format((float) $product['price'], 2) }}
                                </span>
                                <div class="flex items-center justify-between gap-1.5 shrink-0 sm:gap-2">
                                    <flux:button
                                        type="button"
                                        variant="outline"
                                        size="xs"
                                        class="touch-manipulation sm:min-h-0 sm:min-w-0"
                                        x-on:click="$dispatch('open-buy-now', { productId: {{ $product['id'] }} })"
                                    >
                                        {{ __('main.buy_now') }}
                                    </flux:button>
                                    <flux:button
                                        type="button"
                                        variant="ghost"
                                        icon="shopping-cart"
                                        class="!h-9 !min-h-9 !w-9 !min-w-9 !p-0 touch-manipulation [&>div>svg]:size-4 !text-zinc-700 dark:!text-zinc-300 hover:!bg-zinc-100 dark:hover:!bg-zinc-700/60 rounded-md sm:!h-8 sm:!min-h-0 sm:!w-8 sm:!min-w-0"
                                        x-on:click="$store.cart.add(product)"
                                        data-test="cart-add"
                                        aria-label="{{ __('main.add_to_cart_for', ['name' => $product['name']]) }}"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
