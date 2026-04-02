<?php

use App\Actions\Fulfillments\AppendFulfillmentLog;
use App\Actions\Fulfillments\ClaimFulfillment;
use App\Actions\Fulfillments\CompleteFulfillment;
use App\Actions\Fulfillments\CreateFulfillmentsForOrder;
use App\Actions\Fulfillments\FailFulfillment;
use App\Actions\Fulfillments\GetFulfillments;
use App\Actions\Fulfillments\RetryFulfillment;
use App\Actions\Fulfillments\StartFulfillment;
use App\Enums\FulfillmentLogLevel;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductAmountMode;
use App\Events\FulfillmentListChanged;
use App\Models\Category;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $role = Role::firstOrCreate(['name' => 'admin']);
    $role->syncPermissions([
        Permission::firstOrCreate(['name' => 'view_fulfillments']),
        Permission::firstOrCreate(['name' => 'manage_fulfillments']),
        Permission::firstOrCreate(['name' => 'process_refunds']),
    ]);
});

function makeFulfillment(): Fulfillment
{
    $user = User::factory()->create();
    $category = Category::factory()->create([
        'order' => fake()->unique()->numberBetween(1, 1000000),
    ]);
    $package = Package::factory()->create([
        'category_id' => $category->id,
        'order' => fake()->unique()->numberBetween(1, 1000000),
    ]);
    $product = Product::factory()->create(['package_id' => $package->id, 'entry_price' => 20]);

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
    $product = Product::factory()->create(['package_id' => $package->id, 'entry_price' => 20]);

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
    $product = Product::factory()->create(['package_id' => $package->id, 'entry_price' => 15]);

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

test('create fulfillments generates one for custom amount items', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create();
    $product = Product::factory()->create([
        'package_id' => $package->id,
        'entry_price' => 0.01,
        'amount_mode' => ProductAmountMode::Custom,
        'amount_unit_label' => 'crystals',
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => Order::temporaryOrderNumber(),
        'currency' => 'USD',
        'subtotal' => 95.39,
        'fee' => 0,
        'total' => 95.39,
        'status' => OrderStatus::Paid,
    ]);

    $item = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'package_id' => $package->id,
        'name' => $product->name,
        'unit_price' => 95.39,
        'quantity' => 3,
        'amount_mode' => ProductAmountMode::Custom,
        'requested_amount' => 9539,
        'amount_unit_label' => 'crystals',
        'line_total' => 95.39,
        'status' => OrderItemStatus::Pending,
    ]);

    (new CreateFulfillmentsForOrder)->handle($order);
    (new CreateFulfillmentsForOrder)->handle($order);

    $fulfillments = Fulfillment::query()
        ->where('order_item_id', $item->id)
        ->get();

    expect($fulfillments)->toHaveCount(1);
    expect($fulfillments->first()->meta)->toBe([
        'type' => 'custom_amount',
        'amount' => 9539,
        'unit' => 'crystals',
    ]);
});

test('create fulfillments stores requirements payload in meta', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create();
    $product = Product::factory()->create(['package_id' => $package->id, 'entry_price' => 20]);

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
        'requirements_payload' => ['id' => '12345'],
        'status' => OrderItemStatus::Pending,
    ]);

    (new CreateFulfillmentsForOrder)->handle($order);

    $fulfillment = Fulfillment::query()->where('order_item_id', $item->id)->first();

    expect($fulfillment)->not->toBeNull();
    expect(data_get($fulfillment->meta, 'requirements_payload'))->toBe(['id' => '12345']);
});

test('create fulfillments dispatches list changed event', function () {
    Event::fake([FulfillmentListChanged::class]);

    $user = User::factory()->create();
    $package = Package::factory()->create();
    $product = Product::factory()->create(['package_id' => $package->id, 'entry_price' => 30]);

    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => Order::temporaryOrderNumber(),
        'currency' => 'USD',
        'subtotal' => 30,
        'fee' => 0,
        'total' => 30,
        'status' => OrderStatus::Paid,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'package_id' => $package->id,
        'name' => $product->name,
        'unit_price' => 30,
        'quantity' => 1,
        'line_total' => 30,
        'status' => OrderItemStatus::Pending,
    ]);

    (new CreateFulfillmentsForOrder)->handle($order);

    Event::assertDispatched(FulfillmentListChanged::class, function (FulfillmentListChanged $event): bool {
        return $event->type === 'created' && is_int($event->fulfillmentId);
    });
});

test('process fulfillments includes requirements payload in delivered payload', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create();
    $product = Product::factory()->create(['package_id' => $package->id, 'entry_price' => 20]);

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
        'requirements_payload' => ['id' => '99999'],
        'status' => OrderItemStatus::Pending,
    ]);

    (new CreateFulfillmentsForOrder)->handle($order);

    $this->artisan('fulfillment:process', ['--only-pending' => true])->assertExitCode(0);

    $fulfillment = Fulfillment::query()->where('order_id', $order->id)->first();

    expect($fulfillment)->not->toBeNull();
    expect(data_get($fulfillment->meta, 'delivered_payload.requirements_payload'))
        ->toBe(['id' => '99999']);
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

test('fulfillment lifecycle dispatches list changed events', function () {
    Event::fake([FulfillmentListChanged::class]);

    $fulfillment = makeFulfillment();

    (new StartFulfillment)->handle($fulfillment, 'system');
    (new CompleteFulfillment)->handle($fulfillment, ['code' => 'BROADCAST'], 'system');

    Event::assertDispatchedTimes(FulfillmentListChanged::class, 2);
    Event::assertDispatched(FulfillmentListChanged::class, function (FulfillmentListChanged $event): bool {
        return in_array($event->type, ['processing', 'completed'], true) && is_int($event->fulfillmentId);
    });
});

test('single claim success marks fulfillment as processing and claimed', function () {
    $supervisor = User::factory()->create();
    $supervisor->givePermissionTo('manage_fulfillments');

    $fulfillment = makeFulfillment();

    $claimed = app(ClaimFulfillment::class)->handle($fulfillment, $supervisor->id);

    expect($claimed->claimed_by)->toBe($supervisor->id);
    expect($claimed->claimed_at)->not->toBeNull();
    expect($claimed->status)->toBe(FulfillmentStatus::Processing);
});

test('get fulfillments can filter by claimed supervisor', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $firstSupervisor = User::factory()->create();
    $secondSupervisor = User::factory()->create();

    $firstFulfillment = makeFulfillment();
    $firstFulfillment->update([
        'status' => FulfillmentStatus::Processing,
        'claimed_by' => $firstSupervisor->id,
        'claimed_at' => now(),
    ]);

    $secondFulfillment = makeFulfillment();
    $secondFulfillment->update([
        'status' => FulfillmentStatus::Processing,
        'claimed_by' => $secondSupervisor->id,
        'claimed_at' => now(),
    ]);

    $filtered = app(GetFulfillments::class)->handle(
        '',
        'all',
        20,
        'all',
        $admin->id,
        true,
        $firstSupervisor->id
    );

    expect($filtered->getCollection()->pluck('id')->all())->toContain($firstFulfillment->id);
    expect($filtered->getCollection()->pluck('id')->all())->not->toContain($secondFulfillment->id);
});

test('get fulfillments can live-filter by customer and order search', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $firstFulfillment = makeFulfillment();
    $secondFulfillment = makeFulfillment();

    $firstOrder = $firstFulfillment->order()->firstOrFail();
    $secondOrder = $secondFulfillment->order()->firstOrFail();
    $firstCustomer = User::query()->findOrFail($firstOrder->user_id);

    $byCustomer = app(GetFulfillments::class)->handle(
        '',
        'all',
        20,
        'all',
        $admin->id,
        true,
        null,
        null,
        $firstCustomer->email,
        null
    );

    expect($byCustomer->getCollection()->pluck('id')->all())->toContain($firstFulfillment->id);
    expect($byCustomer->getCollection()->pluck('id')->all())->not->toContain($secondFulfillment->id);

    $byOrder = app(GetFulfillments::class)->handle(
        '',
        'all',
        20,
        'all',
        $admin->id,
        true,
        null,
        null,
        null,
        $secondOrder->order_number
    );

    expect($byOrder->getCollection()->pluck('id')->all())->toContain($secondFulfillment->id);
    expect($byOrder->getCollection()->pluck('id')->all())->not->toContain($firstFulfillment->id);
});

test('fulfillment supervisor queue shows distinct order reference per unit on same order', function () {
    $supervisor = User::factory()->create();
    $supervisor->givePermissionTo(['view_fulfillments', 'manage_fulfillments']);

    $user = User::factory()->create();
    $category = Category::factory()->create([
        'order' => fake()->unique()->numberBetween(1, 1000000),
    ]);
    $package = Package::factory()->create([
        'category_id' => $category->id,
        'order' => fake()->unique()->numberBetween(1, 1000000),
    ]);
    $product = Product::factory()->create(['package_id' => $package->id, 'entry_price' => 10]);

    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => 'ORD-SUP-REF-X',
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
        'unit_price' => 10,
        'quantity' => 2,
        'line_total' => 20,
        'status' => OrderItemStatus::Pending,
    ]);

    (new CreateFulfillmentsForOrder)->handle($order);

    $fulfillmentIds = Fulfillment::query()->where('order_id', $order->id)->orderBy('id')->pluck('id')->all();

    expect($fulfillmentIds)->toHaveCount(2);

    $refFirst = 'ORD-SUP-REF-XF'.$fulfillmentIds[0];
    $refSecond = 'ORD-SUP-REF-XF'.$fulfillmentIds[1];

    Livewire::actingAs($supervisor)
        ->test('pages::backend.fulfillments.index')
        ->assertSee($refFirst, false)
        ->assertSee($refSecond, false);
});

test('second claim attempt on same fulfillment fails', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $first->givePermissionTo('manage_fulfillments');
    $second->givePermissionTo('manage_fulfillments');

    $fulfillment = makeFulfillment();

    app(ClaimFulfillment::class)->handle($fulfillment, $first->id);

    expect(fn () => app(ClaimFulfillment::class)->handle($fulfillment->refresh(), $second->id))
        ->toThrow(ValidationException::class);

    expect($fulfillment->refresh()->claimed_by)->toBe($first->id);
});

test('claim rejects when supervisor already has five active tasks', function () {
    $supervisor = User::factory()->create();
    $supervisor->givePermissionTo('manage_fulfillments');

    for ($i = 0; $i < 5; $i++) {
        $active = makeFulfillment();
        $active->update([
            'status' => FulfillmentStatus::Processing,
            'claimed_by' => $supervisor->id,
            'claimed_at' => now(),
        ]);
    }

    $next = makeFulfillment();

    expect(fn () => app(ClaimFulfillment::class)->handle($next, $supervisor->id))
        ->toThrow(ValidationException::class);

    expect($next->refresh()->claimed_by)->toBeNull();
    expect($next->status)->toBe(FulfillmentStatus::Queued);
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
    expect($fulfillment->claimed_by)->toBeNull();
    expect($fulfillment->claimed_at)->toBeNull();
    expect($fulfillment->last_error)->toBeNull();

    (new StartFulfillment)->handle($fulfillment, 'system');
    (new CompleteFulfillment)->handle($fulfillment, ['code' => 'RETRY-OK'], 'system');

    $fulfillment->refresh();
    expect($fulfillment->status)->toBe(FulfillmentStatus::Completed);
    expect($fulfillment->orderItem->refresh()->status)->toBe(OrderItemStatus::Fulfilled);
});

test('supervisor cannot update fulfillment claimed by another supervisor', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $owner->givePermissionTo('manage_fulfillments');
    $other->givePermissionTo('manage_fulfillments');

    $fulfillment = makeFulfillment();
    $fulfillment->update([
        'status' => FulfillmentStatus::Processing,
        'claimed_by' => $owner->id,
        'claimed_at' => now(),
    ]);

    expect($other->can('update', $fulfillment->refresh()))->toBeFalse();
});

test('admin can fail fulfillment and refund immediately', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

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
        ->where('reference_type', Fulfillment::class)
        ->where('reference_id', $fulfillment->id)
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
        ->assertDontSeeHtml('wire:click="retryFulfillment(');
});
