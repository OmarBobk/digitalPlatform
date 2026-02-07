<?php

use App\Actions\Orders\RefundOrderItem;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\WalletTransactionDirection;
use App\Enums\WalletTransactionType;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @return array{order: Order, item: OrderItem, fulfillment: Fulfillment}
 */
function makeOrderItem(User $user, FulfillmentStatus $status): array
{
    $package = Package::factory()->create();
    $product = Product::factory()->create([
        'package_id' => $package->id,
        'entry_price' => 20,
    ]);

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
        'status' => $status === FulfillmentStatus::Failed ? OrderItemStatus::Failed : OrderItemStatus::Pending,
    ]);

    $fulfillment = Fulfillment::create([
        'order_id' => $order->id,
        'order_item_id' => $item->id,
        'provider' => 'manual',
        'status' => $status,
        'attempts' => 0,
        'last_error' => $status === FulfillmentStatus::Failed ? 'Provider error' : null,
    ]);

    return [
        'order' => $order,
        'item' => $item,
        'fulfillment' => $fulfillment,
    ];
}

test('refund request creates pending transaction without changing balance', function () {
    $user = User::factory()->create();
    $wallet = Wallet::forUser($user);
    $payload = makeOrderItem($user, FulfillmentStatus::Failed);

    $action = new RefundOrderItem;
    $transaction = $action->handle($payload['fulfillment'], $user->id);

    $wallet->refresh();
    expect((float) $wallet->balance)->toBe(0.0);
    expect($transaction->status)->toBe(WalletTransaction::STATUS_PENDING);

    expect(WalletTransaction::query()
        ->where('reference_type', Fulfillment::class)
        ->where('reference_id', $payload['fulfillment']->id)
        ->count())->toBe(1);
});

test('refund request is idempotent and does not duplicate', function () {
    $user = User::factory()->create();
    Wallet::forUser($user);
    $payload = makeOrderItem($user, FulfillmentStatus::Failed);

    $action = new RefundOrderItem;
    $first = $action->handle($payload['fulfillment'], $user->id);
    $second = $action->handle($payload['fulfillment'], $user->id);

    expect($first->id)->toBe($second->id);
    expect(WalletTransaction::query()
        ->where('reference_type', Fulfillment::class)
        ->where('reference_id', $payload['fulfillment']->id)
        ->count())->toBe(1);
});

test('refund is allowed only when fulfillment failed', function () {
    $user = User::factory()->create();
    Wallet::forUser($user);
    $payload = makeOrderItem($user, FulfillmentStatus::Queued);

    $action = new RefundOrderItem;

    expect(fn () => $action->handle($payload['fulfillment'], $user->id))
        ->toThrow(ValidationException::class);

    expect(WalletTransaction::count())->toBe(0);
});

test('retry is blocked when refund pending and allowed when failed', function () {
    $user = User::factory()->create();
    Wallet::forUser($user);
    $payload = makeOrderItem($user, FulfillmentStatus::Failed);

    Livewire::actingAs($user)
        ->test('pages::frontend.order-details', ['order' => $payload['order']])
        ->call('retryFulfillment', $payload['fulfillment']->id)
        ->assertSet('actionMessage', __('messages.fulfillment_marked_queued'));

    $payload['fulfillment']->refresh();
    expect($payload['fulfillment']->status)->toBe(FulfillmentStatus::Queued);

    $payload = makeOrderItem($user, FulfillmentStatus::Failed);
    (new RefundOrderItem)->handle($payload['fulfillment'], $user->id);

    Livewire::actingAs($user)
        ->test('pages::frontend.order-details', ['order' => $payload['order']])
        ->call('retryFulfillment', $payload['fulfillment']->id)
        ->assertSet('actionMessage', __('messages.retry_not_allowed'));
});

test('refund request is denied for other user items', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Wallet::forUser($user);
    $payload = makeOrderItem($otherUser, FulfillmentStatus::Failed);

    $action = new RefundOrderItem;

    expect(fn () => $action->handle($payload['fulfillment'], $user->id))
        ->toThrow(ValidationException::class);
});

test('refund request creates credit pending ledger entry', function () {
    $user = User::factory()->create();
    Wallet::forUser($user);
    $payload = makeOrderItem($user, FulfillmentStatus::Failed);

    $transaction = (new RefundOrderItem)->handle($payload['fulfillment'], $user->id);

    expect($transaction->type)->toBe(WalletTransactionType::Refund);
    expect($transaction->direction)->toBe(WalletTransactionDirection::Credit);
    expect($transaction->status)->toBe(WalletTransaction::STATUS_PENDING);
});
