<?php

use App\Actions\Orders\CheckoutFromPayload;
use App\Actions\Packages\ResolvePackageRequirements;
use App\Enums\OrderStatus;
use App\Models\Product;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::frontend')] class extends Component {

    public ?string $checkoutError = null;
    public ?string $checkoutSuccess = null;
    public ?string $lastOrderNumber = null;
    public array $requirementsByProduct = [];
    public array $cartRequirements = [];

    public function checkout(mixed $items): void
    {
        $this->reset('checkoutError', 'checkoutSuccess', 'lastOrderNumber');

        if (! auth()->check()) {
            $this->checkoutError = 'Please sign in to complete checkout.';
            return;
        }

        $user = auth()->user();

        if (! $this->validateCartItems($items)) {
            return;
        }

        try {
            $order = app(CheckoutFromPayload::class)->handle(
                $user,
                $items,
                [
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]
            );

            if (! $order->exists || $order->status !== OrderStatus::Paid) {
                $this->checkoutError = 'Checkout could not be completed.';
                return;
            }

            $this->checkoutSuccess = 'Payment successful. Order '.$order->order_number.' is processing.';
            $this->lastOrderNumber = $order->order_number;
            $this->dispatch('checkout-success', orderNumber: $order->order_number);
        } catch (ValidationException $exception) {
            $this->checkoutError = collect($exception->errors())->flatten()->first()
                ?? 'Checkout validation failed.';
        } catch (\Throwable) {
            $this->checkoutError = 'Something went wrong while processing your checkout.';
        }
    }

    public function loadRequirements(array $productIds): void
    {
        $ids = collect($productIds)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return;
        }

        $resolver = app(ResolvePackageRequirements::class);
        $resolvedByProduct = [];
        $schemaByProduct = [];

        $products = Product::query()
            ->with('package.requirements')
            ->whereIn('id', $ids)
            ->get();

        foreach ($products as $product) {
            $resolved = $resolver->handle($product->package?->requirements ?? collect());
            $resolvedByProduct[$product->id] = $resolved;
            $schemaByProduct[$product->id] = $resolved['schema'];
        }

        $this->requirementsByProduct = $resolvedByProduct;
        $this->dispatch('requirements-loaded', requirements: $schemaByProduct);
    }

    public function validateCartRequirement(int $productId, string $key, mixed $value): void
    {
        $resolved = $this->resolveRequirementsForProduct($productId);

        if ($resolved['rules'] === [] || ! array_key_exists($key, $resolved['rules'])) {
            $this->dispatch('cart-requirement-validation', productId: $productId, key: $key, message: null);
            return;
        }

        data_set($this->cartRequirements, $productId.'.'.$key, $value);

        [$rules, $attributes] = $this->buildCartRulesForProduct($productId, $resolved);

        try {
            $this->validateOnly("cartRequirements.$productId.$key", $rules, [], $attributes);
            $this->dispatch('cart-requirement-validation', productId: $productId, key: $key, message: null);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();
            $this->dispatch('cart-requirement-validation', productId: $productId, key: $key, message: $message);
        }
    }

    public function render(): View
    {
        return $this->view()->title(__('main.shopping_cart'));
    }

    private function validateCartItems(mixed $items): bool
    {
        if (! is_array($items)) {
            return true;
        }

        $this->syncCartRequirementsFromItems($items);
        [$rules, $attributes] = $this->buildCartRulesForItems($items);

        if ($rules === []) {
            $this->dispatch('cart-requirement-errors', errors: []);
            return true;
        }

        try {
            $this->validate($rules, [], $attributes);
            $this->dispatch('cart-requirement-errors', errors: []);
            return true;
        } catch (ValidationException $exception) {
            $this->dispatchCartRequirementErrors($exception->errors());
            $this->checkoutError = collect($exception->errors())->flatten()->first()
                ?? 'Checkout validation failed.';

            return false;
        }
    }

    private function resolveRequirementsForProduct(int $productId): array
    {
        if (isset($this->requirementsByProduct[$productId])) {
            return $this->requirementsByProduct[$productId];
        }

        $product = Product::query()
            ->with('package.requirements')
            ->whereKey($productId)
            ->first();

        if ($product === null) {
            return ['schema' => [], 'rules' => [], 'attributes' => []];
        }

        $resolved = app(ResolvePackageRequirements::class)
            ->handle($product->package?->requirements ?? collect());

        $this->requirementsByProduct[$productId] = $resolved;

        return $resolved;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{0: array<string, array<int, string>>, 1: array<string, string>}
     */
    private function buildCartRulesForItems(array $items): array
    {
        $rules = [];
        $attributes = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $productId = (int) ($item['product_id'] ?? $item['id'] ?? 0);

            if ($productId <= 0) {
                continue;
            }

            $resolved = $this->resolveRequirementsForProduct($productId);
            [$itemRules, $itemAttributes] = $this->buildCartRulesForProduct($productId, $resolved);

            $rules = array_merge($rules, $itemRules);
            $attributes = array_merge($attributes, $itemAttributes);
        }

        return [$rules, $attributes];
    }

    /**
     * @return array{0: array<string, array<int, string>>, 1: array<string, string>}
     */
    private function buildCartRulesForProduct(int $productId, array $resolved): array
    {
        $rules = [];
        $attributes = [];

        foreach ($resolved['rules'] ?? [] as $key => $ruleSet) {
            $rules["cartRequirements.$productId.$key"] = $ruleSet;
            $attributes["cartRequirements.$productId.$key"] = $resolved['attributes'][$key] ?? $key;
        }

        return [$rules, $attributes];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncCartRequirementsFromItems(array $items): void
    {
        $requirements = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $productId = (int) ($item['product_id'] ?? $item['id'] ?? 0);

            if ($productId <= 0) {
                continue;
            }

            $payload = $item['requirements'] ?? $item['requirements_payload'] ?? [];

            $requirements[$productId] = is_array($payload) ? $payload : [];
        }

        $this->cartRequirements = $requirements;
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    private function dispatchCartRequirementErrors(array $errors): void
    {
        $mapped = [];

        foreach ($errors as $field => $messages) {
            if (! str_starts_with($field, 'cartRequirements.')) {
                continue;
            }

            $segments = explode('.', $field, 3);

            if (count($segments) < 3) {
                continue;
            }

            $productId = $segments[1];
            $key = $segments[2];
            $message = is_array($messages) ? ($messages[0] ?? null) : $messages;

            if ($message === null) {
                continue;
            }

            $mapped[$productId][$key] = $message;
        }

        $this->dispatch('cart-requirement-errors', errors: $mapped);
    }

};
?>

<div
    class="mx-auto w-full max-w-7xl px-3 py-6 sm:px-0 sm:py-10"
    x-data
    x-init="
        $store.cart.init();
        $store.cart.setValidationMessages({
            required: @js(__('messages.required_field')),
            numeric: @js(__('messages.numeric')),
            min_chars: @js(__('messages.min_chars')),
            max_chars: @js(__('messages.max_chars')),
            min_value: @js(__('messages.min_value')),
            max_value: @js(__('messages.max_value')),
            min_digits: @js(__('messages.min_digits')),
            max_digits: @js(__('messages.max_digits')),
            in: @js(__('messages.in_values')),
            invalid_format: @js(__('messages.invalid_format')),
            invalid_value: @js(__('messages.invalid_value')),
        });
        if ($store.cart.items.length) {
            $wire.loadRequirements($store.cart.items.map(item => item.id));
        }
    "
    x-on:requirements-loaded.window="$store.cart.applyRequirementsSchema($event.detail.requirements)"
    x-on:cart-requirement-validation.window="$store.cart.setServerRequirementError($event.detail)"
    x-on:cart-requirement-errors.window="$store.cart.setServerRequirementErrors($event.detail.errors)"
    x-on:checkout-success.window="$store.cart.clear()"
    data-test="cart-page"
>
    <div class="mb-4 flex items-center">
        <x-back-button />
    </div>
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
                                    <div class="mt-1 text-sm font-semibold text-(--color-accent)" dir="ltr" x-text="$store.cart.format(item.price)"></div>
                                </div>
                            </div>

                            <template x-if="item.requirements_schema && item.requirements_schema.length">
                                <div class="mt-3 w-full sm:mt-0 sm:w-auto">
                                    <div class="grid gap-2 sm:min-w-[18rem] sm:grid-cols-2">
                                        <template x-for="requirement in item.requirements_schema" :key="requirement.key">
                                            <label class="flex flex-col gap-1 text-xs text-zinc-600 dark:text-zinc-300">
                                                <span class="text-[11px] uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                                    <span x-text="requirement.label || requirement.key"></span>
                                                    <span x-show="requirement.is_required" class="text-amber-600 dark:text-amber-400">*</span>
                                                </span>
                                                <template x-if="requirement.type === 'select' && Array.isArray(requirement.options) && requirement.options.length">
                                                    <select
                                                        class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-700 shadow-sm outline-none transition focus:border-(--color-accent) dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200"
                                                        x-bind:class="{
                                                            'border-red-500 focus:border-red-500': $store.cart.getRequirementError(item, requirement)
                                                        }"
                                                        x-on:change="
                                                            $store.cart.updateRequirement(item.id, requirement.key, $event.target.value);
                                                            $wire.validateCartRequirement(item.id, requirement.key, $event.target.value);
                                                        "
                                                        :value="item.requirements?.[requirement.key] ?? ''"
                                                    >
                                                        <option value="">--</option>
                                                        <template x-for="option in requirement.options" :key="option">
                                                            <option :value="option" x-text="option"></option>
                                                        </template>
                                                    </select>
                                                </template>
                                                <template x-if="!(requirement.type === 'select' && Array.isArray(requirement.options) && requirement.options.length)">
                                                    <input
                                                        class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-700 shadow-sm outline-none transition focus:border-(--color-accent) dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200"
                                                        x-bind:class="{
                                                            'border-red-500 focus:border-red-500': $store.cart.getRequirementError(item, requirement)
                                                        }"
                                                        :type="requirement.type === 'number' ? 'number' : 'text'"
                                                        :placeholder="requirement.key"
                                                        x-on:input="$store.cart.updateRequirement(item.id, requirement.key, $event.target.value)"
                                                        x-on:blur="$wire.validateCartRequirement(item.id, requirement.key, $event.target.value)"
                                                        :value="item.requirements?.[requirement.key] ?? ''"
                                                    />
                                                </template>
                                                <span
                                                    class="text-[11px] text-red-600 dark:text-red-400"
                                                    x-show="$store.cart.getRequirementError(item, requirement)"
                                                    x-text="$store.cart.getRequirementError(item, requirement)"
                                                ></span>
                                            </label>
                                        </template>
                                    </div>
                                </div>
                            </template>

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

                                <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100" dir="ltr" x-text="$store.cart.format(item.price * item.quantity)"></div>

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

                @if ($checkoutError)
                    <flux:callout variant="subtle" icon="exclamation-triangle" class="mt-4">
                        {{ $checkoutError }}
                    </flux:callout>
                @endif

                @if ($checkoutSuccess)
                    <flux:callout variant="subtle" icon="check-circle" class="mt-4">
                        <div class="space-y-3">
                            <div>{{ $checkoutSuccess }}</div>
                            @if ($lastOrderNumber)
                                <div class="flex flex-wrap gap-2">
                                    <flux:button
                                        as="a"
                                        href="{{ route('orders.show', $lastOrderNumber) }}"
                                        wire:navigate
                                        variant="outline"
                                        size="sm"
                                    >
                                        {{ __('messages.view_order') }}
                                    </flux:button>
                                </div>
                            @endif
                        </div>
                    </flux:callout>
                @endif

                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between text-zinc-600 dark:text-zinc-300">
                        <span>Ara toplam</span>
                        <span class="font-semibold text-zinc-900 dark:text-zinc-100" dir="ltr" x-text="$store.cart.format($store.cart.subtotal)"></span>
                    </div>
                    <div class="flex items-center justify-between text-zinc-600 dark:text-zinc-300">
                        <span>Kargo</span>
                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">Ücretsiz</span>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-between border-t border-zinc-100 pt-4 text-base font-semibold dark:border-zinc-700">
                    <span class="text-zinc-900 dark:text-zinc-100">Toplam</span>
                    <span class="text-(--color-accent)" dir="ltr" x-text="$store.cart.format($store.cart.subtotal)"></span>
                </div>

                <div class="mt-4 space-y-3">
                    <flux:button
                        variant="primary"
                        class="w-full justify-center !bg-accent !text-accent-foreground hover:!bg-accent-hover"
                        x-bind:disabled="$store.cart.count === 0 || $store.cart.hasMissingRequirements"
                        x-on:click="$wire.checkout($store.cart.items)"
                        wire:loading.attr="disabled"
                        wire:target="checkout"
                        data-test="cart-checkout"
                    >
                        Ödemeye geç
                    </flux:button>
                    <p
                        class="text-xs text-zinc-500 dark:text-zinc-400"
                        x-show="$store.cart.hasMissingRequirements"
                    >
                        {{ __('messages.requirements_required_checkout') }}
                    </p>
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
