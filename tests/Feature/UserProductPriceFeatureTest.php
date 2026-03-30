<?php

use App\Actions\Orders\CreateOrderFromCartPayload;
use App\Actions\UserProductPrices\CreateUserProductPrice;
use App\Enums\LoyaltyTier;
use App\Enums\ProductAmountMode;
use App\Livewire\Users\UserProductPrices;
use App\Models\LoyaltyTierConfig;
use App\Models\Package;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\User;
use App\Models\UserProductPrice;
use App\Notifications\OrderPriceFlooredNotification;
use App\Services\CustomerPriceService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'manage_users', 'guard_name' => 'web']);

    PricingRule::query()->create([
        'min_price' => 0,
        'max_price' => 999999.99,
        'retail_percentage' => 10,
        'wholesale_percentage' => 2,
        'priority' => 0,
        'is_active' => true,
    ]);

    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'salesperson', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    foreach (['customer', 'salesperson'] as $role) {
        LoyaltyTierConfig::query()->upsert(
            [
                ['role' => $role, 'name' => 'bronze', 'min_spend' => 0, 'discount_percentage' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['role' => $role, 'name' => 'silver', 'min_spend' => 500, 'discount_percentage' => 5, 'created_at' => now(), 'updated_at' => now()],
                ['role' => $role, 'name' => 'gold', 'min_spend' => 2000, 'discount_percentage' => 10, 'created_at' => now(), 'updated_at' => now()],
            ],
            ['role', 'name'],
            ['min_spend', 'discount_percentage', 'updated_at']
        );
    }

    Permission::firstOrCreate(['name' => 'manage_user_prices', 'guard_name' => 'web']);
});

test('user product price applies fixed adjustment (delta) before loyalty discount', function (): void {
    $user = User::factory()->create(['loyalty_tier' => LoyaltyTier::Gold]);
    $user->assignRole('customer');
    $product = Product::factory()->create(['entry_price' => 100]);

    UserProductPrice::query()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'price' => -5.00,
        'created_by' => null,
    ]);

    $service = app(CustomerPriceService::class);
    $overrides = $service->getUserOverridesFor($user);
    $result = $service->priceFor($product, $user, $overrides);

    expect($result['base_price'])->toBe(105.0);
    expect($result['discount_amount'])->toBe(5.0);
    expect($result['final_price'])->toBe(100.0);
    expect($result['tier_name'])->toBe('gold');
    expect($result['meta']['is_override'])->toBeTrue();
    expect($result['meta']['is_floor_applied'])->toBeTrue();
    expect($result['meta']['is_below_cost'])->toBeFalse();
});

test('user product price adjustment follows derived base price changes (entry price updates)', function (): void {
    $user = User::factory()->create(['loyalty_tier' => LoyaltyTier::Gold]);
    $user->assignRole('customer');
    $product = Product::factory()->create(['entry_price' => 100]);

    UserProductPrice::query()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'price' => -5.00,
        'created_by' => null,
    ]);

    $service = app(CustomerPriceService::class);
    $resultBefore = $service->priceFor($product, $user);

    expect($resultBefore['base_price'])->toBe(105.0);

    $product->update(['entry_price' => 150]);

    $resultAfter = app(CustomerPriceService::class)->priceFor($product->refresh(), $user);
    expect($resultAfter['base_price'])->toBe(160.0);
    expect($resultAfter['meta']['is_override'])->toBeTrue();
});

test('user product price above entry is not below cost', function (): void {
    $user = User::factory()->create();
    $user->assignRole('customer');
    $product = Product::factory()->create(['entry_price' => 100]);

    UserProductPrice::query()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'price' => 150.00,
        'created_by' => null,
    ]);

    $result = app(CustomerPriceService::class)->priceFor($product, $user);

    expect($result['meta']['is_below_cost'])->toBeFalse();
});

test('no override falls back to existing customer price logic', function (): void {
    $user = User::factory()->create(['loyalty_tier' => LoyaltyTier::Gold]);
    $user->assignRole('customer');
    $product = Product::factory()->create(['entry_price' => 100]);

    $result = app(CustomerPriceService::class)->priceFor($product, $user);

    expect($result['base_price'])->toBe(110.0);
    expect($result['discount_amount'])->toBe(10.0);
    expect($result['final_price'])->toBe(100.0);
    expect($result['meta']['is_override'])->toBeFalse();
    expect($result['meta']['is_floor_applied'])->toBeTrue();
});

test('finalPriceForAmount returns expected structure and pricing values', function (): void {
    $user = User::factory()->create(['loyalty_tier' => LoyaltyTier::Gold]);
    $user->assignRole('customer');

    $product = Product::factory()->create([
        'entry_price' => 0.01,
        'amount_mode' => ProductAmountMode::Custom,
        'custom_amount_min' => 1,
        'custom_amount_max' => 200000,
        'custom_amount_step' => 1,
    ]);

    $result = app(CustomerPriceService::class)->finalPriceForAmount($product, 9539, $user);

    expect($result)->toHaveKeys(['base_price', 'discount_amount', 'final_price', 'tier_name', 'meta']);
    expect($result['base_price'])->toBe(104.93);
    expect($result['discount_amount'])->toBe(9.54);
    expect($result['final_price'])->toBe(95.39);
    expect($result['tier_name'])->toBe('gold');
    expect(data_get($result, 'meta.is_floor_applied'))->toBeTrue();
});

test('order item uses user product price adjustment when creating order', function (): void {
    $user = User::factory()->create();
    $package = Package::factory()->create();
    $product = Product::factory()->create([
        'package_id' => $package->id,
        'entry_price' => 100,
    ]);

    UserProductPrice::query()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'price' => 42.50,
        'created_by' => null,
    ]);

    $order = app(CreateOrderFromCartPayload::class)->handle($user, [
        [
            'product_id' => $product->id,
            'package_id' => $package->id,
            'quantity' => 3,
        ],
    ], null, false);

    $item = $order->items->first();
    expect($item)->not->toBeNull();
    expect((float) $item->unit_price)->toBe(152.5);
    expect((float) $item->line_total)->toBe(457.5);
});

test('sends admin notification when order item price is clamped to entry price floor', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $user = User::factory()->create(['loyalty_tier' => LoyaltyTier::Gold]);
    $user->assignRole('customer');
    $package = Package::factory()->create();
    $product = Product::factory()->create([
        'package_id' => $package->id,
        'entry_price' => 100,
    ]);

    UserProductPrice::query()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'price' => -5.00,
        'created_by' => null,
    ]);

    app(CreateOrderFromCartPayload::class)->handle($user, [
        [
            'product_id' => $product->id,
            'package_id' => $package->id,
            'quantity' => 1,
        ],
    ]);

    $admin->refresh();
    $notification = $admin->notifications()->latest()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->type)->toBe(OrderPriceFlooredNotification::class);
});

test('create duplicate user product price fails validation', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo('manage_user_prices');

    $target = User::factory()->create();
    $product = Product::factory()->create();

    UserProductPrice::query()->create([
        'user_id' => $target->id,
        'product_id' => $product->id,
        'price' => 10.00,
        'created_by' => $admin->id,
    ]);

    expect(fn () => app(CreateUserProductPrice::class)->handle($target, [
        'product_id' => $product->id,
        'price' => 20.00,
    ], $admin))->toThrow(ValidationException::class);
});

test('user product prices modal shows catalog entry retail and wholesale for selected product', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(['manage_users', 'manage_user_prices']);

    $target = User::factory()->create();
    $product = Product::factory()->create([
        'entry_price' => 100,
        'is_active' => true,
    ]);

    $sym = config('billing.currency_symbol', '$');

    Livewire::actingAs($admin)
        ->test(UserProductPrices::class, ['user' => $target])
        ->call('openCreate')
        ->call('selectProduct', $product->id)
        ->assertSee(__('messages.user_product_price_catalog_context'))
        ->assertSee($sym.'110.00')
        ->assertSee($sym.'102.00');
});
