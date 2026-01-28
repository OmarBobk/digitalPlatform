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
use Illuminate\Foundation\Testing\RefreshDatabase;

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
