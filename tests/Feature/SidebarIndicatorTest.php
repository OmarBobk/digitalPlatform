<?php

use App\Enums\FulfillmentStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\TopupMethod;
use App\Enums\TopupRequestStatus;
use App\Livewire\Sidebar\FulfillmentIndicator;
use App\Livewire\Sidebar\TopupIndicator;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Product;
use App\Models\TopupRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Date::setTestNow(now());

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->syncPermissions([
        Permission::firstOrCreate(['name' => 'view_fulfillments']),
        Permission::firstOrCreate(['name' => 'manage_topups']),
    ]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('fulfillment indicator updates when failed fulfillments exist', function () {
    actingAs($this->admin);
    Livewire::actingAs($this->admin);

    $component = Livewire::test(FulfillmentIndicator::class);
    $component->assertSet('count', 0)
        ->assertDontSee('bg-amber-500');

    $productOwner = User::factory()->create();
    $package = Package::factory()->create();
    $product = Product::factory()->create([
        'package_id' => $package->id,
        'entry_price' => 25,
    ]);

    $order = Order::create([
        'user_id' => $productOwner->id,
        'order_number' => Order::temporaryOrderNumber(),
        'currency' => 'USD',
        'subtotal' => 25,
        'fee' => 0,
        'total' => 25,
        'status' => OrderStatus::Paid,
    ]);

    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'package_id' => $package->id,
        'name' => $product->name,
        'quantity' => 1,
        'line_total' => 25,
        'unit_price' => 25,
        'status' => OrderItemStatus::Pending,
    ]);

    Fulfillment::create([
        'order_id' => $order->id,
        'order_item_id' => $orderItem->id,
        'provider' => 'manual',
        'status' => FulfillmentStatus::Failed,
        'attempts' => 1,
        'last_error' => 'Test failure',
    ]);

    $component->call('refreshCount')
        ->assertSet('count', 1)
        ->assertSee('bg-amber-500')
        ->assertSee('1');
});

test('topup indicator updates when pending topups exist', function () {
    actingAs($this->admin);
    Livewire::actingAs($this->admin);

    $component = Livewire::test(TopupIndicator::class);
    $component->assertSet('count', 0)
        ->assertDontSee('bg-amber-500');

    $requestOwner = User::factory()->create();

    TopupRequest::create([
        'user_id' => $requestOwner->id,
        'wallet_id' => null,
        'method' => TopupMethod::ShamCash,
        'amount' => 40,
        'currency' => 'USD',
        'status' => TopupRequestStatus::Pending,
    ]);

    $component->call('refreshCount')
        ->assertSet('count', 1)
        ->assertSee('bg-amber-500')
        ->assertSee('1');
});
