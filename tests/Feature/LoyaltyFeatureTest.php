<?php

use App\Actions\Loyalty\EvaluateLoyaltyForUserAction;
use App\Enums\FulfillmentStatus;
use App\Enums\LoyaltyTier;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Fulfillment;
use App\Models\LoyaltyTierConfig;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use App\Services\CustomerPriceService;
use App\Services\LoyaltySpendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'customer']);
    Role::firstOrCreate(['name' => 'salesperson']);
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
});

test('customer price service returns base price when user is null', function (): void {
    $product = Product::factory()->create(['entry_price' => 100]);
    \App\Models\PricingRule::create([
        'min_price' => 0,
        'max_price' => 9999,
        'retail_percentage' => 10,
        'wholesale_percentage' => 0,
        'priority' => 0,
        'is_active' => true,
    ]);

    $service = app(CustomerPriceService::class);
    $result = $service->priceFor($product, null);

    expect($result['discount_amount'])->toBe(0.0);
    expect($result['final_price'])->toBe($result['base_price']);
    expect($result['tier_name'])->toBeNull();
});

test('customer price service applies tier discount for user with gold tier', function (): void {
    \App\Models\PricingRule::create([
        'min_price' => 0,
        'max_price' => 9999,
        'retail_percentage' => 0,
        'wholesale_percentage' => 0,
        'priority' => 0,
        'is_active' => true,
    ]);
    $user = User::factory()->create(['loyalty_tier' => LoyaltyTier::Gold]);
    $user->assignRole('customer');
    $product = Product::factory()->create(['entry_price' => 100]);

    $service = app(CustomerPriceService::class);
    $result = $service->priceFor($product, $user);

    expect($result['base_price'])->toBe(100.0);
    expect($result['discount_amount'])->toBe(10.0);
    expect($result['final_price'])->toBe(90.0);
    expect($result['tier_name'])->toBe('gold');
});

test('loyalty evaluate command updates user tier from spend', function (): void {
    $user = User::factory()->create();
    $user->assignRole('customer');
    $package = Package::factory()->create();
    $product = Product::factory()->create(['package_id' => $package->id]);
    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => Order::temporaryOrderNumber(),
        'currency' => 'USD',
        'subtotal' => 600,
        'fee' => 0,
        'total' => 600,
        'status' => OrderStatus::Paid,
    ]);
    $item = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'package_id' => $package->id,
        'name' => $product->name,
        'unit_price' => 600,
        'entry_price' => 500,
        'quantity' => 1,
        'line_total' => 600,
        'status' => OrderItemStatus::Pending,
    ]);
    Fulfillment::create([
        'order_id' => $order->id,
        'order_item_id' => $item->id,
        'provider' => 'manual',
        'status' => FulfillmentStatus::Completed,
        'attempts' => 1,
        'completed_at' => now(),
    ]);

    $action = app(EvaluateLoyaltyForUserAction::class);
    $action->handle($user);

    $user->refresh();
    expect($user->loyalty_tier)->toBe(LoyaltyTier::Silver);
    expect($user->loyalty_evaluated_at)->not->toBeNull();
});

test('loyalty spend service excludes refunded fulfillments', function (): void {
    $user = User::factory()->create();
    $wallet = \App\Models\Wallet::forUser($user);
    $package = Package::factory()->create();
    $product = Product::factory()->create(['package_id' => $package->id]);
    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => Order::temporaryOrderNumber(),
        'currency' => 'USD',
        'subtotal' => 100,
        'fee' => 0,
        'total' => 100,
        'status' => OrderStatus::Paid,
    ]);
    $item = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'package_id' => $package->id,
        'name' => $product->name,
        'unit_price' => 100,
        'entry_price' => 80,
        'quantity' => 1,
        'line_total' => 100,
        'status' => OrderItemStatus::Pending,
    ]);
    $fulfillment = Fulfillment::create([
        'order_id' => $order->id,
        'order_item_id' => $item->id,
        'provider' => 'manual',
        'status' => FulfillmentStatus::Completed,
        'attempts' => 1,
        'completed_at' => now(),
    ]);

    $service = app(LoyaltySpendService::class);
    expect($service->computeRollingSpend($user, 90))->toBe(100.0);

    \App\Models\WalletTransaction::create([
        'wallet_id' => $wallet->id,
        'type' => \App\Enums\WalletTransactionType::Refund,
        'direction' => \App\Enums\WalletTransactionDirection::Credit,
        'amount' => 100,
        'status' => 'posted',
        'reference_type' => Fulfillment::class,
        'reference_id' => $fulfillment->id,
    ]);

    expect($service->computeRollingSpend($user, 90))->toBe(0.0);
});
