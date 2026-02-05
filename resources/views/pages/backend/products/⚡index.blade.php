<?php

use App\Actions\Products\DeleteProduct;
use App\Actions\Products\GetProductDetails;
use App\Actions\Products\GetProductPackages;
use App\Actions\Products\GetProducts;
use App\Actions\Products\ToggleProductStatus;
use App\Actions\Products\UpsertProduct;
use App\Models\Product;
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
    public ?string $productRetailPrice = null;
    public ?string $productWholesalePrice = null;
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
            'productRetailPrice' => ['required', 'numeric', 'min:0'],
            'productWholesalePrice' => ['required', 'numeric', 'min:0'],
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
                'retail_price' => $validated['productRetailPrice'],
                'wholesale_price' => $validated['productWholesalePrice'],
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
        $this->productRetailPrice = (string) $product->retail_price;
        $this->productWholesalePrice = (string) $product->wholesale_price;
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
            'productRetailPrice',
            'productWholesalePrice',
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
    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                <div class="flex size-12 items-center justify-center rounded-xl bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                    <flux:icon icon="shopping-cart" class="size-5" />
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
                            <flux:badge class="capitalize">
                                {{ $statusFilter === 'active' ? __('messages.active') : __('messages.inactive_status') }}
                            </flux:badge>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <flux:button
                    type="button"
                    variant="primary"
                    icon="plus"
                    class="!bg-accent !text-accent-foreground hover:!bg-accent-hover"
                    x-on:click="toggleProductForm()"
                >
                    {{ __('messages.new_product') }}
                </flux:button>
                <flux:button
                    type="button"
                    variant="ghost"
                    icon="adjustments-horizontal"
                    x-on:click="showFilters = !showFilters"
                >
                    {{ __('messages.filters') }}
                </flux:button>
                <flux:button
                    type="button"
                    variant="ghost"
                    icon="arrow-path"
                    wire:click="$refresh"
                    wire:loading.attr="disabled"
                >
                    {{ __('messages.refresh') }}
                </flux:button>
            </div>
        </div>

        <form
            class="grid gap-4 pt-4"
            wire:submit.prevent="applyFilters"
            x-show="showFilters"
            x-cloak
            data-test="products-filters"
        >
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
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
                        <flux:select.option value="retail_price">{{ __('messages.retail_price') }}</flux:select.option>
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
            <div class="flex flex-wrap justify-between sm:justify-start gap-0 sm:gap-3">
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
                <div class="flex flex-wrap h-full items-end gap-2">
                    <flux:button type="submit" variant="primary" icon="magnifying-glass">
                        {{ __('messages.apply') }}
                    </flux:button>
                    <flux:button type="button" variant="ghost" icon="arrow-path" wire:click="resetFilters">
                        {{ __('messages.reset') }}
                    </flux:button>
                </div>
            </div>
        </form>
    </section>

    <section
        class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900"
        x-show="showProductForm"
        x-cloak
    >
        <form class="grid gap-5" wire:submit.prevent="saveProduct">
            <div class="flex flex-col gap-1">
                <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">
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
                        name="productRetailPrice"
                        label="{{ __('messages.retail_price') }}"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="{{ __('messages.retail_price_placeholder') }}"
                        wire:model.defer="productRetailPrice"
                    />
                    @error('productRetailPrice')
                        <flux:text color="red">{{ $message }}</flux:text>
                    @enderror
                </div>
                <div class="grid gap-2">
                    <flux:input
                        class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                        name="productWholesalePrice"
                        label="{{ __('messages.wholesale_price') }}"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="{{ __('messages.wholesale_price_placeholder') }}"
                        wire:model.defer="productWholesalePrice"
                    />
                    @error('productWholesalePrice')
                        <flux:text color="red">{{ $message }}</flux:text>
                    @enderror
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

    <section class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
            <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">
                {{ __('messages.products') }}
            </flux:heading>
            <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                <span>{{ __('messages.total') }}</span>
                <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $this->products->total() }}</span>
            </div>
        </div>

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
                    <div class="flex flex-col items-center gap-3 p-10 text-center">
                        <div class="flex size-12 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-300">
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
                        <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                            <tr>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.product') }}</th>
                                <th class="px-5 py-3 text-start font-semibold hidden sm:table-cell">{{ __('messages.package') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.retail_price') }}</th>
                                <th class="px-5 py-3 text-start font-semibold hidden lg:table-cell">{{ __('messages.wholesale_price') }}</th>
                                <th class="px-5 py-3 text-start font-semibold hidden xl:table-cell">{{ __('messages.order') }}</th>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('messages.status') }}</th>
                                <th class="px-5 py-3 text-end font-semibold">{{ __('messages.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->products as $product)
                                <tr
                                    class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/60"
                                    wire:key="product-{{ $product->id }}"
                                >
                                    <td class="px-5 py-4">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="truncate font-semibold text-zinc-900 dark:text-zinc-100">
                                                    {{ $product->name }}
                                                </span>
                                                @if ($product->serial)
                                                    <span class="rounded-md bg-zinc-100 px-2 py-0.5 text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                                        {{ $product->serial }}
                                                    </span>
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
                                    <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                                        {{ $product->retail_price }}
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400 lg:hidden">
                                            {{ __('messages.wholesale_price') }}: {{ $product->wholesale_price }}
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300 hidden lg:table-cell">
                                        {{ $product->wholesale_price }}
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
                <flux:spacer />
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
