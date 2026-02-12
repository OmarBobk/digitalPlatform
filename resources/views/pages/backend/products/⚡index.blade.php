<?php

use App\Actions\Products\DeleteProduct;
use App\Actions\Products\GetProductDetails;
use App\Actions\Products\GetProductPackages;
use App\Actions\Products\GetProducts;
use App\Actions\Products\ToggleProductStatus;
use App\Actions\Products\UpsertProduct;
use App\Models\Product;
use App\Services\PriceCalculator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public string $sortBy = 'order';
    public string $sortDirection = 'asc';
    public int $perPage = 10;

    public ?int $editingProductId = null;
    public ?int $productPackageId = null;
    public ?string $productSerial = null;
    public string $productName = '';
    public ?string $productEntryPrice = null;
    public ?int $productOrder = null;
    public bool $productIsActive = true;

    public bool $showDeleteProductModal = false;
    public ?int $deleteProductId = null;
    public string $deleteProductName = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('manage_products'), 403);
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'sortBy', 'sortDirection', 'perPage']);
        $this->resetPage();
    }

    /**
     * @return array<string, mixed>
     */
    protected function productRules(): array
    {
        return [
            'productPackageId' => ['required', 'integer', 'exists:packages,id'],
            'productSerial' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'serial')->ignore($this->editingProductId),
            ],
            'productName' => ['required', 'string', 'max:255'],
            'productEntryPrice' => ['required', 'numeric', 'min:0'],
            'productOrder' => [
                'required',
                'integer',
                'min:0',
                Rule::unique('products', 'order')->ignore($this->editingProductId),
            ],
            'productIsActive' => ['boolean'],
        ];
    }

    public function saveProduct(): void
    {
        $validated = $this->validate($this->productRules());

        app(UpsertProduct::class)->handle(
            $this->editingProductId,
            [
                'package_id' => $validated['productPackageId'],
                'serial' => $validated['productSerial'],
                'name' => $validated['productName'],
                'entry_price' => $validated['productEntryPrice'],
                'is_active' => $validated['productIsActive'],
                'order' => $validated['productOrder'],
            ]
        );

        $this->resetProductForm();
        $this->resetPage();
        $this->dispatch('product-saved');
    }

    public function startEditProduct(int $productId): void
    {
        $product = app(GetProductDetails::class)->handle($productId);

        if ($product === null) {
            return;
        }

        $this->editingProductId = $product->id;
        $this->productPackageId = $product->package_id;
        $this->productSerial = $product->serial;
        $this->productName = $product->name;
        $this->productEntryPrice = $product->entry_price !== null ? (string) $product->entry_price : null;
        $this->productOrder = $product->order;
        $this->productIsActive = $product->is_active;

        $this->dispatch('open-product-panel');
    }

    public function toggleStatus(int $productId): void
    {
        app(ToggleProductStatus::class)->handle($productId);
    }

    public function confirmDeleteProduct(int $productId): void
    {
        $product = app(GetProductDetails::class)->handle($productId);

        if ($product === null) {
            return;
        }

        $this->deleteProductId = $product->id;
        $this->deleteProductName = $product->name;
        $this->showDeleteProductModal = true;
    }

    public function cancelDeleteProduct(): void
    {
        $this->reset(['showDeleteProductModal', 'deleteProductId', 'deleteProductName']);
    }

    public function deleteProduct(?int $productId = null): void
    {
        $productId = $productId ?? $this->deleteProductId;

        if ($productId === null) {
            return;
        }

        app(DeleteProduct::class)->handle($productId, auth()->id());

        $this->cancelDeleteProduct();
        $this->resetPage();
    }

    public function resetProductForm(): void
    {
        $this->reset([
            'editingProductId',
            'productPackageId',
            'productSerial',
            'productName',
            'productEntryPrice',
            'productOrder',
            'productIsActive',
        ]);
        $this->resetValidation();
    }

    public function getProductsProperty(): LengthAwarePaginator
    {
        return app(GetProducts::class)->handle(
            $this->search,
            $this->statusFilter,
            $this->sortBy,
            $this->sortDirection,
            $this->perPage
        );
    }

    public function getPackagesProperty(): Collection
    {
        return app(GetProductPackages::class)->handle();
    }

    /**
     * Live preview: derived retail price for current productEntryPrice (backend source of truth).
     */
    public function getComputedRetailPriceProperty(): ?float
    {
        $entry = $this->productEntryPrice;
        if ($entry === null || $entry === '' || ! is_numeric($entry) || (float) $entry < 0) {
            return null;
        }

        try {
            return app(PriceCalculator::class)->calculate((float) $entry)['retail_price'];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Live preview: derived wholesale price for current productEntryPrice (backend source of truth).
     */
    public function getComputedWholesalePriceProperty(): ?float
    {
        $entry = $this->productEntryPrice;
        if ($entry === null || $entry === '' || ! is_numeric($entry) || (float) $entry < 0) {
            return null;
        }

        try {
            return app(PriceCalculator::class)->calculate((float) $entry)['wholesale_price'];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Uses global min/max so the placeholder reflects actual order bounds.
     */
    public function getOrderRangePlaceholderProperty(): string
    {
        $min = Product::min('order');
        $max = Product::max('order');

        if ($min === null || $max === null) {
            return __('messages.no_orders_yet');
        }

        return $min.' - '.$max;
    }

    public function render(): View
    {
        return $this->view()->title(__('messages.products'));
    }
};
?>

<div
    class="flex h-full w-full flex-1 flex-col gap-6"
    x-data="{
        showFilters: false,
        showProductForm: false,
        toggleProductForm() {
            if (this.showProductForm) {
                this.showProductForm = false;
                $wire.resetProductForm();
                return;
            }

            this.showProductForm = true;
        },
    }"
    x-on:product-saved.window="showProductForm = false"
    x-on:open-product-panel.window="showProductForm = true"
    data-test="products-page"
>
    <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                <div class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-600 dark:bg-sky-950/50 dark:text-sky-400">
                    <flux:icon icon="shopping-cart" class="size-6" />
                </div>
                <div class="flex flex-col gap-2">
                    <div class="flex flex-col gap-1">
                        <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                            {{ __('messages.products') }}
                        </flux:heading>
                        <flux:text class="text-zinc-600 dark:text-zinc-400">
                            {{ __('messages.products_intro') }}
                        </flux:text>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400" role="status" aria-live="polite">
                        <span>{{ __('messages.showing') }}</span>
                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">
                            {{ $this->products->count() }}
                        </span>
                        <span>{{ __('messages.of') }}</span>
                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">
                            {{ $this->products->total() }}
                        </span>
                        <span>{{ __('messages.products') }}</span>
                        @if ($statusFilter !== 'all')
                            <flux:badge color="{{ $statusFilter === 'active' ? 'emerald' : 'amber' }}" size="sm" variant="subtle">
                                {{ $statusFilter === 'active' ? __('messages.active') : __('messages.inactive_status') }}
                            </flux:badge>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <flux:button
                    type="button"
                    variant="primary"
                    icon="plus"
                    class="!bg-accent !text-accent-foreground hover:!bg-accent-hover"
                    x-on:click="toggleProductForm()"
                    x-bind:aria-expanded="showProductForm"
                    aria-controls="product-form-section"
                >
                    {{ __('messages.new_product') }}
                </flux:button>
                <flux:button
                    type="button"
                    variant="outline"
                    icon="adjustments-horizontal"
                    class="border-zinc-300 text-zinc-700 hover:bg-sky-50 hover:border-sky-300 hover:text-sky-700 dark:border-zinc-600 dark:text-zinc-300 dark:hover:bg-sky-950/30 dark:hover:border-sky-800 dark:hover:text-sky-300"
                    x-on:click="showFilters = !showFilters"
                    x-bind:aria-expanded="showFilters"
                    aria-controls="products-filters"
                >
                    {{ __('messages.filters') }}
                </flux:button>
                <flux:button
                    type="button"
                    variant="outline"
                    icon="arrow-path"
                    class="border-zinc-300 text-zinc-700 hover:bg-zinc-50 hover:border-zinc-400 dark:border-zinc-600 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:border-zinc-500"
                    wire:click="$refresh"
                    wire:loading.attr="disabled"
                >
                    {{ __('messages.refresh') }}
                </flux:button>
            </div>
        </div>

        <form
            id="products-filters"
            class="mt-6 rounded-xl border border-zinc-100 bg-zinc-50/80 p-6 dark:border-zinc-800 dark:bg-zinc-800/40"
            wire:submit.prevent="applyFilters"
            x-show="showFilters"
            x-cloak
            data-test="products-filters"
            role="search"
            aria-label="{{ __('messages.filters') }}"
        >
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="grid gap-2">
                    <flux:input
                        name="search"
                        :label="__('messages.search')"
                        :placeholder="__('messages.search_placeholder')"
                        wire:model.defer="search"
                        class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                    />
                </div>
                <div class="grid gap-2">
                    <flux:select class="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                                 name="sortBy" label="{{ __('messages.sort_by') }}" wire:model.defer="sortBy">
                        <flux:select.option value="order">{{ __('messages.order') }}</flux:select.option>
                        <flux:select.option value="name">{{ __('messages.name') }}</flux:select.option>
                        <flux:select.option value="entry_price">{{ __('messages.entry_price') }}</flux:select.option>
                        <flux:select.option value="created_at">{{ __('messages.created') }}</flux:select.option>
                    </flux:select>
                </div>
                <div class="grid gap-2">
                    <flux:select class="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                                 name="sortDirection" label="{{ __('messages.direction') }}" wire:model.defer="sortDirection">
                        <flux:select.option value="asc">{{ __('messages.ascending') }}</flux:select.option>
                        <flux:select.option value="desc">{{ __('messages.descending') }}</flux:select.option>
                    </flux:select>
                </div>
                <div class="grid gap-2">
                    <flux:select class="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                                 name="perPage" label="{{ __('messages.per_page') }}" wire:model.defer="perPage">
                        <flux:select.option value="10">10</flux:select.option>
                        <flux:select.option value="25">25</flux:select.option>
                        <flux:select.option value="50">50</flux:select.option>
                    </flux:select>
                </div>
            </div>
            <div class="mt-6 flex flex-row flex-wrap items-end gap-3">
                <div class="w-full min-w-0 sm:w-auto sm:min-w-[140px]">
                    <flux:select
                        class="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                        name="statusFilter"
                        label="{{ __('messages.status') }}"
                        wire:model.defer="statusFilter"
                    >
                        <flux:select.option value="all">{{ __('messages.all') }}</flux:select.option>
                        <flux:select.option value="active">{{ __('messages.active') }}</flux:select.option>
                        <flux:select.option value="inactive">{{ __('messages.inactive_status') }}</flux:select.option>
                    </flux:select>
                </div>
                <flux:button type="submit" variant="primary" icon="magnifying-glass" class="w-full sm:w-auto !bg-emerald-600 hover:!bg-emerald-700 dark:!bg-emerald-600 dark:hover:!bg-emerald-500">
                    {{ __('messages.apply') }}
                </flux:button>
                <flux:button type="button" variant="outline" icon="arrow-path" wire:click="resetFilters" class="w-full sm:w-auto border-zinc-300 text-zinc-700 hover:bg-zinc-100 dark:border-zinc-600 dark:text-zinc-300 dark:hover:bg-zinc-800">
                    {{ __('messages.reset') }}
                </flux:button>
            </div>
        </form>
    </section>

    <section
        id="product-form-section"
        class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900"
        x-show="showProductForm"
        x-cloak
        role="region"
        aria-labelledby="product-form-heading"
    >
        <form class="grid gap-5" wire:submit.prevent="saveProduct">
            <div class="flex flex-col gap-1">
                <flux:heading id="product-form-heading" size="sm" class="text-zinc-900 dark:text-zinc-100">
                    {{ $editingProductId ? __('messages.edit_product') : __('messages.create_product') }}
                </flux:heading>
                <flux:text class="text-zinc-600 dark:text-zinc-400">
                    {{ $editingProductId ? __('messages.edit_product_hint') : __('messages.product_slug_auto') }}
                </flux:text>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <flux:input
                        class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                        name="productName"
                        label="{{ __('messages.name') }}"
                        placeholder="{{ __('messages.product_name_placeholder') }}"
                        wire:model.defer="productName"
                    />
                    @error('productName')
                        <flux:text color="red">{{ $message }}</flux:text>
                    @enderror
                </div>
                <div class="grid gap-2">
                    <flux:select
                        class="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                        name="productPackageId"
                        label="{{ __('messages.package') }}"
                        wire:model.defer="productPackageId"
                        placeholder="{{ __('messages.select_package') }}"
                    >
                        <flux:select.option value="">{{ __('messages.select_package') }}</flux:select.option>
                        @foreach ($this->packages as $package)
                            <flux:select.option value="{{ $package->id }}">{{ $package->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('productPackageId')
                        <flux:text color="red">{{ $message }}</flux:text>
                    @enderror
                </div>
                <div class="grid gap-2">
                    <flux:input
                        class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                        name="productSerial"
                        label="{{ __('messages.serial') }}"
                        placeholder="{{ __('messages.product_serial_placeholder') }}"
                        wire:model.defer="productSerial"
                    />
                    @error('productSerial')
                        <flux:text color="red">{{ $message }}</flux:text>
                    @enderror
                </div>
                <div class="grid gap-2">
                    <flux:input
                        class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                        name="productOrder"
                        label="{{ __('messages.order') }}"
                        type="number"
                        min="0"
                        step="1"
                        placeholder="{{ $this->orderRangePlaceholder }}"
                        wire:model.defer="productOrder"
                    />
                    @error('productOrder')
                        <flux:text color="red">{{ $message }}</flux:text>
                    @enderror
                </div>
                <div class="grid gap-2">
                    <flux:input
                        class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                        name="productEntryPrice"
                        label="{{ __('messages.entry_price') }}"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="{{ __('messages.entry_price_placeholder') }}"
                        wire:model.live="productEntryPrice"
                    />
                    @error('productEntryPrice')
                        <flux:text color="red">{{ $message }}</flux:text>
                    @enderror
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('messages.derived_prices') }}</span>
                    <div class="flex flex-wrap gap-6 rounded-lg border border-zinc-200 bg-zinc-50/50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('messages.retail_price') }}</span>
                            <span class="tabular-nums font-mono text-base text-zinc-900 dark:text-zinc-100">
                                {{ $this->computedRetailPrice !== null ? number_format($this->computedRetailPrice, 2) : '—' }}
                            </span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('messages.wholesale_price') }}</span>
                            <span class="tabular-nums font-mono text-base text-zinc-900 dark:text-zinc-100">
                                {{ $this->computedWholesalePrice !== null ? number_format($this->computedWholesalePrice, 2) : '—' }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <flux:label>{{ __('messages.active') }}:</flux:label>
                    <flux:switch
                        class="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                        wire:model.defer="productIsActive"
                    />
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:button
                    class="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                    type="submit" variant="primary" icon="plus" wire:loading.attr="disabled" wire:target="saveProduct">
                    {{ $editingProductId ? __('messages.update_product') : __('messages.create_product') }}
                </flux:button>
                <flux:button
                    class="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                    type="button" variant="ghost" x-on:click="toggleProductForm()">
                    {{ __('messages.cancel') }}
                </flux:button>
            </div>
        </form>
    </section>

    <section class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900" aria-labelledby="products-table-heading">

        <div
            class="overflow-x-auto"
            wire:loading.class="opacity-60"
            wire:target="applyFilters,resetFilters,$refresh,nextPage,previousPage,gotoPage"
        >
            <div
                class="p-6"
                wire:loading.delay
                wire:target="applyFilters,resetFilters,$refresh,nextPage,previousPage,gotoPage"
            >
                <div class="grid gap-3">
                    <flux:skeleton class="h-4 w-36" />
                    <div class="grid gap-2">
                        <flux:skeleton class="h-10 w-full" />
                        <flux:skeleton class="h-10 w-full" />
                        <flux:skeleton class="h-10 w-full" />
                        <flux:skeleton class="h-10 w-full" />
                        <flux:skeleton class="h-10 w-full" />
                    </div>
                </div>
            </div>
            <div wire:loading.delay.remove wire:target="applyFilters,resetFilters,$refresh,nextPage,previousPage,gotoPage">
                @if ($this->products->count() === 0)
                    <div class="flex flex-col items-center gap-3 p-10 text-center" role="status" aria-live="polite">
                        <div class="flex size-12 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-300" aria-hidden="true">
                            <flux:icon icon="shopping-cart" class="size-5" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">
                                {{ __('messages.no_products_yet') }}
                            </flux:heading>
                            <flux:text class="text-zinc-600 dark:text-zinc-400">
                                {{ __('messages.create_first_product') }}
                            </flux:text>
                        </div>
                        <flux:button
                            type="button"
                            variant="primary"
                            icon="plus"
                            class="!bg-accent !text-accent-foreground hover:!bg-accent-hover"
                            x-on:click="showProductForm = true"
                        >
                            {{ __('messages.add_product') }}
                        </flux:button>
                    </div>
                @else
                    <table class="min-w-full divide-y divide-zinc-100 text-sm dark:divide-zinc-800" data-test="products-table">
                        <thead class="bg-zinc-100 text-xs uppercase tracking-wide text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                            <tr>
                                <th scope="col" class="px-5 py-3 text-start font-semibold">{{ __('messages.product') }}</th>
                                <th scope="col" class="px-5 py-3 text-start font-semibold hidden sm:table-cell">{{ __('messages.package') }}</th>
                                <th scope="col" class="px-5 py-3 text-start font-semibold">{{ __('messages.entry_price') }}</th>
                                <th scope="col" class="px-5 py-3 text-start font-semibold">{{ __('messages.retail_price') }}</th>
                                <th scope="col" class="px-5 py-3 text-start font-semibold hidden lg:table-cell">{{ __('messages.wholesale_price') }}</th>
                                <th scope="col" class="px-5 py-3 text-start font-semibold hidden xl:table-cell">{{ __('messages.order') }}</th>
                                <th scope="col" class="px-5 py-3 text-start font-semibold">{{ __('messages.status') }}</th>
                                <th scope="col" class="px-5 py-3 text-end font-semibold">{{ __('messages.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->products as $index => $product)
                                @php
                                    $rowBg = $index % 2 === 0 ? 'bg-white dark:bg-zinc-900' : 'bg-zinc-50/50 dark:bg-zinc-800/30';
                                @endphp
                                <tr
                                    class="transition {{ $rowBg }} hover:bg-sky-50/50 dark:hover:bg-sky-950/20"
                                    wire:key="product-{{ $product->id }}"
                                >
                                    <td class="px-5 py-4">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="truncate font-semibold text-zinc-900 dark:text-zinc-100">
                                                    {{ $product->name }}
                                                </span>
                                                @if ($product->serial)
                                                    <flux:badge color="zinc" size="sm" variant="subtle">{{ $product->serial }}</flux:badge>
                                                @endif
                                            </div>
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                @if ($product->package)
                                                    /{{ $product->package->slug }}/{{ $product->slug }}
                                                @else
                                                    /{{ $product->slug }}
                                                @endif
                                            </div>
                                            @if ($product->package)
                                                <div class="text-xs text-zinc-500 dark:text-zinc-400 sm:hidden">
                                                    {{ $product->package->name }}
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300 hidden sm:table-cell">
                                        {{ $product->package?->name ?? __('messages.no_package') }}
                                    </td>
                                    <td class="px-5 py-4 text-end tabular-nums text-zinc-900 dark:text-zinc-100">
                                        {{ $product->entry_price !== null ? number_format((float) $product->entry_price, 2) : '—' }}
                                    </td>
                                    <td class="px-5 py-4 text-end tabular-nums text-zinc-900 dark:text-zinc-100">
                                        {{ number_format($product->retail_price, 2) }}
                                        <div class="mt-0.5 text-left text-xs text-zinc-500 dark:text-zinc-400 lg:hidden">
                                            {{ __('messages.wholesale_price') }} {{ number_format($product->wholesale_price, 2) }}
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-end tabular-nums text-zinc-900 dark:text-zinc-100 hidden lg:table-cell">
                                        {{ number_format($product->wholesale_price, 2) }}
                                    </td>
                                    <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300 hidden xl:table-cell">
                                        {{ $product->order ?? '—' }}
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-center justify-start">
                                            <flux:switch
                                                :checked="$product->is_active"
                                                wire:click="toggleStatus({{ $product->id }})"
                                                aria-label="{{ __('messages.toggle_status_for', ['name' => $product->name]) }}"
                                            />
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-end">
                                        <flux:dropdown position="bottom" align="end">
                                            <flux:button variant="ghost" icon="ellipsis-vertical" />
                                            <flux:menu>
                                                <flux:menu.item icon="pencil" wire:click="startEditProduct({{ $product->id }})">
                                                    {{ __('messages.edit') }}
                                                </flux:menu.item>
                                                <flux:menu.item icon="eye">{{ __('messages.view') }}</flux:menu.item>
                                                <flux:menu.separator />
                                                <flux:menu.item
                                                    variant="danger"
                                                    icon="trash"
                                                    wire:click="confirmDeleteProduct({{ $product->id }})"
                                                >
                                                    {{ __('messages.delete') }}
                                                </flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="border-t border-zinc-100 px-5 py-4 dark:border-zinc-800">
            {{ $this->products->links() }}
        </div>
    </section>

    <flux:modal
        wire:model.self="showDeleteProductModal"
        class="max-w-md"
        variant="floating"
        @close="cancelDeleteProduct"
        @cancel="cancelDeleteProduct"
    >
        <div class="space-y-6">
            <div class="flex items-start gap-4">
                <div class="flex size-11 items-center justify-center rounded-full bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400">
                    <flux:icon icon="trash" class="size-5" />
                </div>
                <div class="space-y-2">
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                        {{ __('messages.delete_product_title') }}
                    </flux:heading>
                    <flux:text class="text-zinc-600 dark:text-zinc-400">
                        {{ __('messages.delete_product_body', ['name' => $deleteProductName]) }}
                    </flux:text>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="grow" aria-hidden="true"></div>
                <flux:button variant="ghost" wire:click="cancelDeleteProduct">
                    {{ __('messages.cancel') }}
                </flux:button>
                <flux:button
                    variant="danger"
                    wire:click="deleteProduct"
                    wire:loading.attr="disabled"
                    wire:target="deleteProduct"
                >
                    {{ __('messages.delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
