<?php

use App\Actions\Fulfillments\AppendFulfillmentLog;
use App\Actions\Fulfillments\CompleteFulfillment;
use App\Actions\Fulfillments\CreateFulfillmentsForOrder;
use App\Actions\Fulfillments\FailFulfillment;
use App\Actions\Fulfillments\RetryFulfillment;
use App\Actions\Fulfillments\StartFulfillment;
use App\Enums\FulfillmentLogLevel;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeFulfillment(): Fulfillment
{
    $user = User::factory()->create();
    $package = Package::factory()->create();
    $product = Product::factory()->create(['package_id' => $package->id, 'retail_price' => 20]);

    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => Order::temporaryOrderNumber(),
        'currency' => 'USD',
        'subtotal' => 20,
        'fee' => 0,
        'total' => 20,
        'status' => OrderStatus::Paid,
    ]);

    $item = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'package_id' => $package->id,
        'name' => $product->name,
        'unit_price' => 20,
        'quantity' => 1,
        'line_total' => 20,
        'status' => OrderItemStatus::Pending,
    ]);

    return Fulfillment::create([
        'order_id' => $order->id,
        'order_item_id' => $item->id,
        'provider' => 'manual',
        'status' => FulfillmentStatus::Queued,
        'attempts' => 0,
    ]);
}

test('create fulfillments action is idempotent and logs queued', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create();
    $product = Product::factory()->create(['package_id' => $package->id, 'retail_price' => 20]);

    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => Order::temporaryOrderNumber(),
        'currency' => 'USD',
        'subtotal' => 40,
        'fee' => 0,
        'total' => 40,
        'status' => OrderStatus::Paid,
    ]);

    $items = [
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'package_id' => $package->id,
            'name' => $product->name,
            'unit_price' => 20,
            'quantity' => 1,
            'line_total' => 20,
            'status' => OrderItemStatus::Pending,
        ]),
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'package_id' => $package->id,
            'name' => $product->name,
            'unit_price' => 20,
            'quantity' => 1,
            'line_total' => 20,
            'status' => OrderItemStatus::Pending,
        ]),
    ];

    $action = new CreateFulfillmentsForOrder;
    $action->handle($order);
    $action->handle($order);

    $fulfillments = Fulfillment::query()->where('order_id', $order->id)->get();

    expect($fulfillments)->toHaveCount(2);
    expect($fulfillments->pluck('status')->unique()->all())->toBe([FulfillmentStatus::Queued]);
    expect($fulfillments->pluck('order_item_id')->sort()->values()->all())
        ->toBe(collect($items)->pluck('id')->sort()->values()->all());

    $logCount = $fulfillments->sum(fn (Fulfillment $fulfillment) => $fulfillment->logs()->count());
    expect($logCount)->toBe(2);
});

test('create fulfillments generates one per quantity', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create();
    $product = Product::factory()->create(['package_id' => $package->id, 'retail_price' => 15]);

    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => Order::temporaryOrderNumber(),
        'currency' => 'USD',
        'subtotal' => 45,
        'fee' => 0,
        'total' => 45,
        'status' => OrderStatus::Paid,
    ]);

    $item = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'package_id' => $package->id,
        'name' => $product->name,
        'unit_price' => 15,
        'quantity' => 3,
        'line_total' => 45,
        'status' => OrderItemStatus::Pending,
    ]);

    (new CreateFulfillmentsForOrder)->handle($order);
    (new CreateFulfillmentsForOrder)->handle($order);

    $fulfillments = Fulfillment::query()
        ->where('order_item_id', $item->id)
        ->get();

    expect($fulfillments)->toHaveCount(3);
    expect($fulfillments->pluck('status')->unique()->all())->toBe([FulfillmentStatus::Queued]);
});

test('create fulfillments stores requirements payload in meta', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create();
    $product = Product::factory()->create(['package_id' => $package->id, 'retail_price' => 20]);

    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => Order::temporaryOrderNumber(),
        'currency' => 'USD',
        'subtotal' => 20,
        'fee' => 0,
        'total' => 20,
        'status' => OrderStatus::Paid,
    ]);

    $item = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'package_id' => $package->id,
        'name' => $product->name,
        'unit_price' => 20,
        'quantity' => 1,
        'line_total' => 20,
        'requirements_payload' => ['player_id' => '12345'],
        'status' => OrderItemStatus::Pending,
    ]);

    (new CreateFulfillmentsForOrder)->handle($order);

    $fulfillment = Fulfillment::query()->where('order_item_id', $item->id)->first();

    expect($fulfillment)->not->toBeNull();
    expect(data_get($fulfillment->meta, 'requirements_payload'))->toBe(['player_id' => '12345']);
});

test('process fulfillments includes requirements payload in delivered payload', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create();
    $product = Product::factory()->create(['package_id' => $package->id, 'retail_price' => 20]);

    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => Order::temporaryOrderNumber(),
        'currency' => 'USD',
        'subtotal' => 20,
        'fee' => 0,
        'total' => 20,
        'status' => OrderStatus::Paid,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'package_id' => $package->id,
        'name' => $product->name,
        'unit_price' => 20,
        'quantity' => 1,
        'line_total' => 20,
        'requirements_payload' => ['player_id' => '99999'],
        'status' => OrderItemStatus::Pending,
    ]);

    (new CreateFulfillmentsForOrder)->handle($order);

    $this->artisan('fulfillment:process', ['--only-pending' => true])->assertExitCode(0);

    $fulfillment = Fulfillment::query()->where('order_id', $order->id)->first();

    expect($fulfillment)->not->toBeNull();
    expect(data_get($fulfillment->meta, 'delivered_payload.requirements_payload'))
        ->toBe(['player_id' => '99999']);
});

test('append fulfillment log stores context', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create();
    $product = Product::factory()->create(['package_id' => $package->id]);

    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => Order::temporaryOrderNumber(),
        'currency' => 'USD',
        'subtotal' => 10,
        'fee' => 0,
        'total' => 10,
        'status' => OrderStatus::Paid,
    ]);

    $item = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'package_id' => $package->id,
        'name' => $product->name,
        'unit_price' => 10,
        'quantity' => 1,
        'line_total' => 10,
        'status' => OrderItemStatus::Pending,
    ]);

    $fulfillment = Fulfillment::create([
        'order_id' => $order->id,
        'order_item_id' => $item->id,
        'provider' => 'manual',
        'status' => FulfillmentStatus::Queued,
        'attempts' => 0,
    ]);

    $action = new AppendFulfillmentLog;
    $log = $action->handle($fulfillment, FulfillmentLogLevel::Warning, 'Manual review needed', [
        'code' => 'manual_review',
    ]);

    expect($log->level)->toBe(FulfillmentLogLevel::Warning);
    expect($log->context)->toBe(['code' => 'manual_review']);
});

test('queued to processing to completed writes logs and completed_at', function () {
    $fulfillment = makeFulfillment();

    (new StartFulfillment)->handle($fulfillment, 'system');
    $fulfillment->refresh();

    expect($fulfillment->status)->toBe(FulfillmentStatus::Processing);
    expect($fulfillment->processed_at)->not->toBeNull();

    (new CompleteFulfillment)->handle($fulfillment, ['code' => 'ABC-123'], 'system');
    $fulfillment->refresh();

    expect($fulfillment->status)->toBe(FulfillmentStatus::Completed);
    expect($fulfillment->completed_at)->not->toBeNull();
    expect(data_get($fulfillment->meta, 'delivered_payload'))->toBe(['code' => 'ABC-123']);
    expect($fulfillment->orderItem->refresh()->status)->toBe(OrderItemStatus::Fulfilled);

    $logMessages = $fulfillment->logs()->pluck('message')->all();
    expect($logMessages)->toContain('Fulfillment started', 'Fulfillment completed');
});

test('completing twice does not double log', function () {
    $fulfillment = makeFulfillment();

    (new CompleteFulfillment)->handle($fulfillment, ['code' => 'FIRST'], 'system');
    (new CompleteFulfillment)->handle($fulfillment, ['code' => 'SECOND'], 'system');

    $fulfillment->refresh();

    expect($fulfillment->status)->toBe(FulfillmentStatus::Completed);
    expect(data_get($fulfillment->meta, 'delivered_payload'))->toBe(['code' => 'FIRST']);
    expect($fulfillment->logs()->where('message', 'Fulfillment completed')->count())->toBe(1);
});

test('failed fulfillment can be retried and completed', function () {
    $fulfillment = makeFulfillment();

    (new FailFulfillment)->handle($fulfillment, 'Provider outage', 'system');
    $fulfillment->refresh();

    expect($fulfillment->status)->toBe(FulfillmentStatus::Failed);
    expect($fulfillment->last_error)->toBe('Provider outage');
    expect($fulfillment->orderItem->refresh()->status)->toBe(OrderItemStatus::Failed);

    (new RetryFulfillment)->handle($fulfillment, 'admin', 1);
    $fulfillment->refresh();

    expect($fulfillment->status)->toBe(FulfillmentStatus::Queued);
    expect($fulfillment->last_error)->toBeNull();

    (new StartFulfillment)->handle($fulfillment, 'system');
    (new CompleteFulfillment)->handle($fulfillment, ['code' => 'RETRY-OK'], 'system');

    $fulfillment->refresh();
    expect($fulfillment->status)->toBe(FulfillmentStatus::Completed);
    expect($fulfillment->orderItem->refresh()->status)->toBe(OrderItemStatus::Fulfilled);
});

test('admin can fail fulfillment and refund immediately', function () {
    $role = Role::firstOrCreate(['name' => 'admin']);
    $admin = User::factory()->create();
    $admin->assignRole($role);

    $fulfillment = makeFulfillment();
    $order = Order::query()->findOrFail($fulfillment->order_id);
    $item = OrderItem::query()->findOrFail($fulfillment->order_item_id);

    Livewire::actingAs($admin)
        ->test('pages::backend.fulfillments.index')
        ->set('selectedFulfillmentId', $fulfillment->id)
        ->set('failureReason', 'Provider failed')
        ->set('refundAfterFail', true)
        ->call('failFulfillment');

    $fulfillment->refresh();
    $order->refresh();
    $wallet = Wallet::query()->where('user_id', $order->user_id)->first();

    expect($fulfillment->status)->toBe(FulfillmentStatus::Failed);
    expect(data_get($fulfillment->meta, 'refund.status'))->toBe(WalletTransaction::STATUS_POSTED);
    expect($order->status)->toBe(OrderStatus::Refunded);
    expect($wallet)->not->toBeNull();
    expect((float) $wallet->balance)->toBe((float) $item->line_total);

    $transaction = WalletTransaction::query()
        ->where('reference_type', Order::class)
        ->where('reference_id', $order->id)
        ->first();

    expect($transaction)->not->toBeNull();
    expect($transaction->status)->toBe(WalletTransaction::STATUS_POSTED);
    expect($transaction->type)->toBe(\App\Enums\WalletTransactionType::Refund);
    expect($transaction->direction)->toBe(\App\Enums\WalletTransactionDirection::Credit);

    Livewire::actingAs($admin)
        ->test('pages::backend.fulfillments.index')
        ->assertDontSeeHtml('wire:click="markProcessing(')
        ->assertDontSeeHtml('wire:click="openCompleteModal(')
        ->assertDontSeeHtml('wire:click="openFailModal(')
        ->assertDontSeeHtml('wire:click="retryFulfillment(')
        ->assertSeeHtml('wire:click="openDetails(');
});
