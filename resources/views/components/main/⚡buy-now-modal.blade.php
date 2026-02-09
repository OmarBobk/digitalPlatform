<?php

declare(strict_types=1);

use App\Actions\Orders\CheckoutFromPayload;
use App\Actions\Packages\ResolvePackageRequirements;
use App\Enums\OrderStatus;
use App\Models\Package;
use App\Models\Product;
use App\Services\CustomerPriceService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component
{
    public bool $showBuyNowModal = false;
    public ?int $buyNowProductId = null;
    public ?string $buyNowProductName = null;
    public ?string $buyNowPackageName = null;
    /** @var int|string Livewire can send empty string from number input; we normalize to int. */
    public int|string $buyNowQuantity = 1;
    public array $buyNowRequirements = [];
    public array $buyNowRequirementsSchema = [];
    public array $buyNowRequirementsRules = [];
    public array $buyNowRequirementsAttributes = [];
    public ?string $buyNowError = null;
    public ?string $buyNowSuccess = null;
    public ?string $buyNowOrderNumber = null;
    public bool $isPackageOverlayOpen = false;
    public bool $showPackageProducts = false;
    public ?int $selectedPackageId = null;
    public ?string $selectedPackageName = null;
    public array $packageProducts = [];

    public function openBuyNow(int $productId, bool $fromPackageOverlay = false, ?int $quantity = null): void
    {
        if (! auth()->check()) {
            $this->redirectRoute('login');
            return;
        }

        $this->reset('buyNowError', 'buyNowSuccess', 'buyNowOrderNumber');
        $this->resetValidation();

        $product = $this->loadProductForBuyNow($productId);

        if ($product === null) {
            return;
        }

        $this->buyNowProductId = $product['id'];
        $this->buyNowProductName = $product['name'] ?? null;
        $this->buyNowPackageName = $product['package_name'] ?? $this->selectedPackageName;
        $this->buyNowQuantity = max(1, (int) ($quantity ?? 1));
        $this->buyNowRequirementsSchema = $product['requirements_schema'] ?? [];
        $this->buyNowRequirementsRules = $product['requirements_rules'] ?? [];
        $this->buyNowRequirementsAttributes = $product['requirements_attributes'] ?? [];
        $this->buyNowRequirements = [];

        foreach ($this->buyNowRequirementsSchema as $requirement) {
            if (! empty($requirement['key'])) {
                $this->buyNowRequirements[$requirement['key']] = '';
            }
        }

        $this->showBuyNowModal = true;
        $this->showPackageProducts = false;
        $this->isPackageOverlayOpen = $fromPackageOverlay;

        if (! $fromPackageOverlay) {
            $this->selectedPackageId = null;
            $this->selectedPackageName = null;
            $this->packageProducts = [];
        }
    }

    public function closeBuyNow(): void
    {
        $this->reset([
            'showBuyNowModal',
            'buyNowProductId',
            'buyNowProductName',
            'buyNowPackageName',
            'buyNowQuantity',
            'buyNowRequirements',
            'buyNowRequirementsSchema',
            'buyNowRequirementsRules',
            'buyNowRequirementsAttributes',
            'buyNowError',
            'buyNowSuccess',
            'buyNowOrderNumber',
            'isPackageOverlayOpen',
            'showPackageProducts',
            'selectedPackageId',
            'selectedPackageName',
            'packageProducts',
        ]);
        $this->resetValidation();
    }

    public function updatedBuyNowQuantity(mixed $value): void
    {
        $this->buyNowQuantity = max(1, (int) $this->buyNowQuantity);
        $this->validateOnly('buyNowQuantity', $this->buyNowRules(), [], $this->buyNowAttributes());
    }

    public function updatedBuyNowRequirements(mixed $value, string $key): void
    {
        $this->validateOnly("buyNowRequirements.$key", $this->buyNowRules(), [], $this->buyNowAttributes());
    }

    public function submitBuyNow(): void
    {
        $this->reset('buyNowError', 'buyNowSuccess', 'buyNowOrderNumber');

        if (! auth()->check()) {
            $this->redirectRoute('login');
            return;
        }

        if ($this->buyNowProductId === null) {
            $this->buyNowError = __('messages.product_missing');
            return;
        }

        $product = $this->loadProductForBuyNow($this->buyNowProductId);

        if ($product === null) {
            $this->buyNowError = __('messages.product_missing');
            return;
        }

        $this->buyNowQuantity = max(1, (int) $this->buyNowQuantity);
        $this->validate($this->buyNowRules(), [], $this->buyNowAttributes());

        try {
            $order = app(CheckoutFromPayload::class)->handle(
                auth()->user(),
                [[
                    'product_id' => $this->buyNowProductId,
                    'package_id' => $product['package_id'] ?? null,
                    'quantity' => $this->buyNowQuantity,
                    'requirements' => $this->buyNowRequirements,
                ]],
                [
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]
            );

            if (! $order->exists || $order->status !== OrderStatus::Paid) {
                $this->buyNowError = __('messages.checkout_could_not_complete');
                return;
            }

            $message = __('messages.payment_successful_order_processing', ['order_number' => $order->order_number]);
            $this->dispatch('cart-toast', message: $message);
            $this->closeBuyNow();
        } catch (ValidationException $exception) {
            $this->buyNowError = collect($exception->errors())->flatten()->first()
                ?? __('messages.checkout_validation_failed');
        } catch (\Throwable $e) {
            report($e);
            $this->buyNowError = __('messages.something_went_wrong_checkout');
        }
    }

    public function openPackageOverlay(int $packageId): void
    {
        $this->reset('buyNowError', 'buyNowSuccess', 'buyNowOrderNumber');
        $this->resetValidation();
        $this->resetBuyNowState();

        $package = Package::query()
            ->select(['id', 'name'])
            ->with(['products' => function ($query): void {
                $query->select(['id', 'package_id', 'name', 'slug', 'entry_price', 'retail_price', 'order', 'is_active'])
                    ->with([
                        'package:id,name,image,is_active',
                        'package.requirements:id,package_id,key,label,type,is_required,validation_rules,order',
                    ])
                    ->where('is_active', true)
                    ->orderBy('order')
                    ->orderBy('name');
            }])
            ->whereKey($packageId)
            ->where('is_active', true)
            ->first();

        if ($package === null) {
            return;
        }

        $placeholderImage = asset('images/promotions/promo-placeholder.svg');
        $resolver = app(ResolvePackageRequirements::class);
        $priceService = app(CustomerPriceService::class);
        $user = auth()->user();

        $this->packageProducts = $package->products
            ->map(fn (Product $product): array => $this->mapProduct($product, $resolver, $priceService, $user, $placeholderImage))
            ->all();
        $this->selectedPackageId = $package->id;
        $this->selectedPackageName = $package->name;
        $this->isPackageOverlayOpen = true;
        $this->showPackageProducts = true;
        $this->showBuyNowModal = true;
    }

    public function backToPackageProducts(): void
    {
        if (! $this->isPackageOverlayOpen) {
            return;
        }

        $this->reset('buyNowError', 'buyNowSuccess', 'buyNowOrderNumber');
        $this->resetValidation();
        $this->resetBuyNowState();
        $this->showPackageProducts = true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function buyNowRules(): array
    {
        $rules = [
            'buyNowQuantity' => ['required', 'integer', 'min:1'],
        ];

        foreach ($this->buyNowRequirementsRules as $key => $ruleSet) {
            $rules["buyNowRequirements.$key"] = $ruleSet;
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    private function buyNowAttributes(): array
    {
        $attributes = [
            'buyNowQuantity' => __('messages.quantity'),
        ];

        foreach ($this->buyNowRequirementsAttributes as $key => $label) {
            $attributes["buyNowRequirements.$key"] = $label;
        }

        return $attributes;
    }

    public function getBuyNowCanSubmitProperty(): bool
    {
        if ($this->showPackageProducts) {
            return false;
        }

        if ($this->buyNowProductId === null || $this->buyNowSuccess) {
            return false;
        }

        if ((int) $this->buyNowQuantity < 1) {
            return false;
        }

        if ($this->hasBuyNowErrors()) {
            return false;
        }

        foreach ($this->buyNowRequirementsSchema as $requirement) {
            if (empty($requirement['is_required']) || empty($requirement['key'])) {
                continue;
            }

            $value = $this->buyNowRequirements[$requirement['key']] ?? null;

            if (blank($value)) {
                return false;
            }
        }

        return true;
    }

    private function hasBuyNowErrors(): bool
    {
        foreach ($this->getErrorBag()->keys() as $key) {
            if ($key === 'buyNowQuantity' || str_starts_with($key, 'buyNowRequirements.')) {
                return true;
            }
        }

        return false;
    }

    private function resetBuyNowState(): void
    {
        $this->buyNowProductId = null;
        $this->buyNowProductName = null;
        $this->buyNowPackageName = null;
        $this->buyNowQuantity = 1;
        $this->buyNowRequirements = [];
        $this->buyNowRequirementsSchema = [];
        $this->buyNowRequirementsRules = [];
        $this->buyNowRequirementsAttributes = [];
    }

    /**
     * @return array{
     *   id: int,
     *   package_id: int|null,
     *   package_name: string|null,
     *   name: string,
     *   price: mixed,
     *   href: string,
     *   image: string,
     *   requirements_schema: array<int, array<string, mixed>>,
     *   requirements_rules: array<string, array<int, string>>,
     *   requirements_attributes: array<string, string>
     * }|null
     */
    private function loadProductForBuyNow(int $productId): ?array
    {
        $product = Product::query()
            ->select(['id', 'package_id', 'name', 'slug', 'entry_price', 'retail_price', 'order', 'is_active'])
            ->with([
                'package:id,name,image,is_active',
                'package.requirements:id,package_id,key,label,type,is_required,validation_rules,order',
            ])
            ->whereKey($productId)
            ->where('is_active', true)
            ->first();

        if ($product === null) {
            return null;
        }

        $placeholderImage = asset('images/promotions/promo-placeholder.svg');
        $resolver = app(ResolvePackageRequirements::class);
        $priceService = app(CustomerPriceService::class);
        $user = auth()->user();

        return $this->mapProduct($product, $resolver, $priceService, $user, $placeholderImage);
    }

    private function mapProduct(Product $product, ResolvePackageRequirements $resolver, CustomerPriceService $priceService, ?\App\Models\User $user, string $placeholderImage): array
    {
        $resolved = $resolver->handle($product->package?->requirements ?? collect());
        $prices = $priceService->priceFor($product, $user);

        return [
            'id' => $product->id,
            'package_id' => $product->package_id,
            'package_name' => $product->package?->name,
            'name' => $product->name,
            'price' => $prices['final_price'],
            'base_price' => $prices['base_price'],
            'discount_amount' => $prices['discount_amount'],
            'tier_name' => $prices['tier_name'],
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

<flux:modal

    wire:model.self="showBuyNowModal"
    variant="floating"
    class="max-w-3xl"
    @close="closeBuyNow"
    @cancel="closeBuyNow"
>
    <div
        class="relative space-y-4"
        x-data="{ showPackages: @entangle('showPackageProducts') }"
        x-on:open-buy-now.window="$wire.openBuyNow($event.detail.productId, false, $event.detail.quantity)"
        x-on:open-package-overlay.window="$wire.openPackageOverlay($event.detail.packageId)"
    >
        <div
            x-data="cartToastOnModal(@js(__('main.add_to_cart_for')))"
            x-on:cart-item-added.window="notify($event.detail)"
            x-on:cart-toast.window="notify($event.detail)"
            class="pointer-events-none absolute top-3 z-10 flex w-full flex-col gap-2 px-2 sm:max-w-sm ltr:right-3 rtl:left-3 sm:px-0"
            aria-live="polite"
        >
            <template x-for="toast in toasts" :key="toast.id">
                <div
                    x-show="toast.visible"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 translate-y-2"
                    class="pointer-events-auto flex items-center gap-3 rounded-xl border border-emerald-200 bg-white px-4 py-3 text-sm text-emerald-700 shadow-lg dark:border-emerald-500/30 dark:bg-zinc-900 dark:text-emerald-300"
                >
                    <flux:icon icon="check-circle" class="size-4 text-emerald-600 dark:text-emerald-300" />
                    <span class="font-semibold" x-text="toast.message"></span>
                </div>
            </template>
        </div>
        <div class="flex items-center">
            <div class="flex items-center">
                <template x-if="!showPackages && @js($isPackageOverlayOpen)">
                    <flux:button variant="ghost" size="xs" wire:click="backToPackageProducts">
                        <span class="flex items-center gap-1">
                            <flux:icon icon="chevron-left" class="size-4 rtl:rotate-180" />
                            {{ __('messages.back') }}
                        </span>
                    </flux:button>
                </template>
            </div>
            <div class="flex flex-col flex-1 pe-12 items-center gap-1 text-center">
                @php($packageLabel = $selectedPackageName ?? $buyNowPackageName)
                @if ($packageLabel)
                    <span class="inline-flex items-center rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                        {{ $packageLabel }} @if (!$showPackageProducts) -> {{ $buyNowProductName ?? __('main.buy_now') }} @endif
                    </span>
                @endif
            </div>
            <div class="flex justify-end">
{{--                <flux:button--}}
{{--                    variant="ghost"--}}
{{--                    size="xs"--}}
{{--                    icon="x-mark"--}}
{{--                    wire:click="closeBuyNow"--}}
{{--                    aria-label="{{ __('messages.cancel') }}"--}}
{{--                />--}}
            </div>
        </div>

        <template x-if="showPackages">
            <div
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-1"
                class="space-y-4"
            >
            @if ($packageProducts === [])
                <flux:callout variant="subtle" icon="information-circle">
                    {{ __('messages.no_products_yet') }}
                </flux:callout>
            @else
                <div class="space-y-3">
                    @foreach ($packageProducts as $product)
                        <div
                            x-data="{ product: @js($product), qty: 1 }"
                            class="flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-3 text-sm text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 sm:flex-row sm:items-center sm:justify-between"
                            wire:key="package-product-{{ $product['id'] }}"
                        >
                            <div class="flex justify-between flex-1 space-y-1 text-start">
                                <div class="font-semibold">
                                    {{ $product['name'] }}
                                </div>
                                <div class="tabular-nums text-lg font-semibold text-(--color-accent)" dir="ltr">
                                    ${{ number_format((float) $product['price'], 2) }}
                                </div>
                            </div>

                            <div class="flex items-center gap-3 justify-between">
                                <div class="inline-flex items-center gap-1 rounded-lg border border-zinc-200 bg-white px-1 py-0.5 text-xs font-semibold text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                                    <button
                                        type="button"
                                        class="size-7 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                        x-on:click="qty = Math.max(1, qty - 1)"
                                        aria-label="{{ __('main.decrease') }}"
                                    >
                                        <flux:icon icon="minus" class="size-3" />
                                    </button>
                                    <span class="min-w-6 text-center text-sm" x-text="qty"></span>
                                    <button
                                        type="button"
                                        class="size-7 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                        x-on:click="qty += 1"
                                        aria-label="{{ __('main.increase') }}"
                                    >
                                        <flux:icon icon="plus" class="size-3" />
                                    </button>
                                </div>

                                <div class="flex items-center gap-2">
                                    <flux:button
                                        type="button"
                                        variant="outline"
                                        size="xs"
                                        x-on:click="$store.cart.add(product, qty); qty = 1"
                                    >
                                        {{ __('main.add_to_cart') }}
                                    </flux:button>
                                    <flux:button
                                        type="button"
                                        variant="primary"
                                        size="xs"
                                        x-on:click="$wire.openBuyNow({{ $product['id'] }}, true, qty)"
                                    >
                                        {{ __('main.buy_now') }}
                                    </flux:button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-zinc-200 bg-white p-4 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                    <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        {{ __('messages.description') }}
                    </div>
                    <div class="mt-2">{{ __('messages.package_description_placeholder') }}</div>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-white p-4 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                    <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        {{ __('messages.details') }}
                    </div>
                    <div class="mt-2">{{ __('messages.packages_intro') }}</div>
                </div>
            </div>
            </div>
        </template>


        {{--Buy Now Modal--}}
        <template x-if="!showPackages">
            <div
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-1"
                class="space-y-4"
            >
            <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">
                {{ __('main.buy_now') }}
            </flux:heading>
            @if ($buyNowProductName)
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ $buyNowProductName }}
                </flux:text>
            @endif

            @if ($buyNowError)
                <flux:callout variant="subtle" icon="exclamation-triangle">
                    {{ $buyNowError }}
                </flux:callout>
            @endif

            @if ($buyNowSuccess)
                <flux:callout variant="subtle" icon="check-circle">
                    <div class="space-y-3">
                        <div>{{ $buyNowSuccess }}</div>
                        @if ($buyNowOrderNumber)
                            <flux:button
                                as="a"
                                href="{{ route('orders.show', $buyNowOrderNumber) }}"
                                wire:navigate
                                variant="outline"
                                size="sm"
                            >
                                {{ __('messages.view_order') }}
                            </flux:button>
                        @endif
                    </div>
                </flux:callout>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input
                    class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                    type="number"
                    min="1"
                    name="buyNowQuantity"
                    label="{{ __('messages.quantity') }}"
                    wire:model.blur="buyNowQuantity"
                />
            </div>
            @error('buyNowQuantity')
                <span class="text-[11px] text-red-600 dark:text-red-400">{{ $message }}</span>
            @enderror

            @if ($buyNowRequirementsSchema !== [])
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($buyNowRequirementsSchema as $requirement)
                        @php($requirementKey = $requirement['key'] ?? null)
                        @continue(empty($requirementKey))
                        <label class="flex flex-col gap-1 text-xs text-zinc-600 dark:text-zinc-300">
                            <span class="text-[11px] uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                {{ $requirement['label'] ?? $requirementKey }}
                                @if (! empty($requirement['is_required']))
                                    <span class="text-amber-600 dark:text-amber-400">*</span>
                                @endif
                            </span>
                            @if (($requirement['type'] ?? '') === 'select' && ! empty($requirement['options']))
                                <select
                                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-700 shadow-sm outline-none transition focus:border-(--color-accent) dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200"
                                    wire:model.blur="buyNowRequirements.{{ $requirementKey }}"
                                >
                                    <option value="">--</option>
                                    @foreach ($requirement['options'] as $option)
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input
                                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-700 shadow-sm outline-none transition focus:border-(--color-accent) dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200"
                                    type="{{ ($requirement['type'] ?? '') === 'number' ? 'number' : 'text' }}"
                                    placeholder="{{ $requirementKey }}"
                                    wire:model.live.blur="buyNowRequirements.{{ $requirementKey }}"
                                />
                            @endif
                            @error("buyNowRequirements.$requirementKey")
                                <span class="text-[11px] text-red-600 dark:text-red-400">{{ $message }}</span>
                            @enderror
                        </label>
                    @endforeach
                </div>
            @endif

            <div class="flex flex-wrap justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeBuyNow">
                    {{ __('messages.cancel') }}
                </flux:button>
                <flux:button
                    variant="primary"
                    wire:click="submitBuyNow"
                    wire:loading.attr="disabled"
                    wire:target="submitBuyNow"
                    wire:bind:disabled="{{ ! $this->buyNowCanSubmit }}"
                >
                    {{ __('main.pay_now') }}
                </flux:button>
            </div>
            @if ($errors->has('buyNowRequirements.*'))
                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('messages.requirements_required_checkout') }}
                </p>
            @endif
            </div>
        </template>
    </div>
</flux:modal>
