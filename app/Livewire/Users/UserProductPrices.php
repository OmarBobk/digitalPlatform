<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Actions\UserProductPrices\CreateUserProductPrice;
use App\Actions\UserProductPrices\DeleteUserProductPrice;
use App\Actions\UserProductPrices\UpdateUserProductPrice;
use App\Models\Product;
use App\Models\User;
use App\Models\UserProductPrice;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Masmerise\Toaster\Toastable;

class UserProductPrices extends Component
{
    use Toastable;

    public User $user;

    public bool $showModal = false;

    public ?int $editingRowId = null;

    public ?string $editingProductName = null;

    public string $productSearch = '';

    public ?int $selectedProductId = null;

    public string $price = '';

    public ?string $note = null;

    public function mount(User $user): void
    {
        $this->authorize('view', $user);
    }

    public function openCreate(): void
    {
        $this->authorize('manage_user_prices');
        $this->reset(['editingRowId', 'editingProductName', 'productSearch', 'selectedProductId', 'price', 'note']);
        $this->showModal = true;
    }

    public function openEdit(int $rowId): void
    {
        $this->authorize('manage_user_prices');
        $row = UserProductPrice::query()
            ->where('user_id', $this->user->id)
            ->with('product:id,name,entry_price')
            ->whereKey($rowId)
            ->firstOrFail();

        $this->editingRowId = $row->id;
        $this->editingProductName = $row->product?->name;
        $this->selectedProductId = $row->product_id;
        $this->price = (string) $row->price;
        $this->note = $row->note;
        $this->productSearch = '';
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['editingRowId', 'editingProductName', 'productSearch', 'selectedProductId', 'price', 'note']);
    }

    public function save(CreateUserProductPrice $create, UpdateUserProductPrice $update): void
    {
        $this->authorize('manage_user_prices');
        $admin = auth()->user();
        if ($admin === null) {
            abort(403);
        }

        try {
            if ($this->editingRowId === null) {
                $this->validate([
                    'selectedProductId' => ['required', 'integer', 'exists:products,id'],
                    'price' => ['required', 'numeric', 'min:0'],
                    'note' => ['nullable', 'string', 'max:1000'],
                ]);
                $create->handle($this->user, [
                    'product_id' => $this->selectedProductId,
                    'price' => $this->price,
                    'note' => $this->note,
                ], $admin);
                $this->success(__('messages.user_product_price_saved'));
            } else {
                $this->validate([
                    'price' => ['required', 'numeric', 'min:0'],
                    'note' => ['nullable', 'string', 'max:1000'],
                ]);
                $update->handle($this->editingRowId, $this->user, [
                    'price' => $this->price,
                    'note' => $this->note,
                ], $admin);
                $this->success(__('messages.user_product_price_updated'));
            }
            $this->closeModal();
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    public function deleteRow(int $rowId): void
    {
        $this->authorize('manage_user_prices');
        $admin = auth()->user();
        if ($admin === null) {
            abort(403);
        }

        app(DeleteUserProductPrice::class)->handle($rowId, $this->user, $admin);
        $this->success(__('messages.user_product_price_deleted'));
    }

    public function selectProduct(int $productId): void
    {
        $this->authorize('manage_user_prices');
        $this->selectedProductId = $productId;
    }

    public function clearSelectedProduct(): void
    {
        $this->authorize('manage_user_prices');
        $this->selectedProductId = null;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getFilteredProductsProperty(): Collection
    {
        if (! $this->showModal || $this->editingRowId !== null) {
            return collect();
        }

        return Product::query()
            ->select(['id', 'name'])
            ->where('is_active', true)
            ->when($this->productSearch !== '', function ($q): void {
                $q->where('name', 'like', '%'.$this->productSearch.'%');
            })
            ->orderBy('name')
            ->limit(30)
            ->get();
    }

    /**
     * Read-only catalog prices for the modal (entry + rule-derived retail/wholesale).
     *
     * @return array{entry_price: float|null, retail_price: float, wholesale_price: float}|null
     */
    #[Computed]
    public function productPricingPreview(): ?array
    {
        $productId = $this->selectedProductId;
        if ($productId === null || $productId < 1) {
            return null;
        }

        $product = Product::query()->find($productId);
        if ($product === null) {
            return null;
        }

        $entry = $product->entry_price !== null ? (float) $product->entry_price : null;

        return [
            'entry_price' => $entry,
            'retail_price' => (float) $product->retail_price,
            'wholesale_price' => (float) $product->wholesale_price,
        ];
    }

    public function render(): View
    {
        $rows = UserProductPrice::query()
            ->where('user_id', $this->user->id)
            ->with(['product:id,name,entry_price', 'creator:id,name'])
            ->orderByDesc('id')
            ->get();

        return view('livewire.users.user-product-prices', [
            'rows' => $rows,
        ]);
    }
}
