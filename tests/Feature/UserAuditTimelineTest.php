<?php

use App\Actions\Orders\PayOrderWithWallet;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use App\Services\UserAuditTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $adminRole = Role::firstOrCreate(['name' => 'admin']);
    $manageUsers = Permission::firstOrCreate(['name' => 'manage_users']);
    $adminRole->syncPermissions([$manageUsers]);
});

test('admin can access user audit timeline page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $customer = User::factory()->create(['name' => 'Customer One', 'email' => 'customer@example.com']);

    $this->actingAs($admin)
        ->get(route('admin.users.audit', $customer))
        ->assertOk()
        ->assertSee('data-test="user-audit-timeline-page"', false)
        ->assertSee(__('messages.audit_timeline'))
        ->assertSee('Customer One')
        ->assertSee('customer@example.com');
});

test('non-admin cannot access user audit timeline and gets 404', function () {
    $userWithoutManageUsers = User::factory()->create();
    $customer = User::factory()->create();

    $this->actingAs($userWithoutManageUsers)
        ->get(route('admin.users.audit', $customer))
        ->assertNotFound();
});

test('timeline contains wallet debit from ledger after pay order and no financial system_events', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $customer = User::factory()->create(['name' => 'Pay Customer', 'email' => 'pay@example.com']);
    $wallet = Wallet::create([
        'user_id' => $customer->id,
        'type' => 'customer',
        'balance' => 150,
        'currency' => 'USD',
    ]);

    $package = Package::factory()->create();
    $product = Product::factory()->create([
        'package_id' => $package->id,
        'entry_price' => 100,
    ]);

    $order = Order::create([
        'user_id' => $customer->id,
        'order_number' => Order::temporaryOrderNumber(),
        'currency' => 'USD',
        'subtotal' => 100,
        'fee' => 0,
        'total' => 100,
        'status' => OrderStatus::PendingPayment,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'package_id' => $package->id,
        'name' => $product->name,
        'unit_price' => 100,
        'quantity' => 1,
        'line_total' => 100,
        'status' => OrderItemStatus::Pending,
    ]);

    $action = new PayOrderWithWallet;
    $action->handle($order, $wallet);

    $service = app(UserAuditTimelineService::class);
    $entries = $service->buildForUser($customer, 100);

    $hasWalletTransactionDebit = $entries->contains(function ($dto) {
        return $dto->type === 'wallet_transaction'
            && str_contains(strtolower($dto->title), 'purchase')
            && $dto->isFinancial;
    });
    expect($hasWalletTransactionDebit)->toBeTrue();

    $hasFinancialSystemEvent = $entries->contains(function ($dto) {
        return $dto->sourceKey !== null && str_starts_with($dto->sourceKey, 'system_event:') && $dto->isFinancial;
    });
    expect($hasFinancialSystemEvent)->toBeFalse();

    $this->actingAs($admin)
        ->get(route('admin.users.audit', $customer))
        ->assertOk()
        ->assertSee('purchase', false)
        ->assertSee('debit', false);
});
