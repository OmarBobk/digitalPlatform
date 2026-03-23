<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\BugLinkDetectionService;
use Illuminate\Http\Request;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('detects admin order id from page url path', function () {
    $service = app(BugLinkDetectionService::class);

    $links = $service->detectFromUrl('https://example.com/admin/orders/42?tab=1');

    expect($links)->toHaveCount(1)
        ->and($links[0])->toMatchArray(['type' => 'order', 'reference_id' => 42]);
});

it('resolves storefront order from order_number segment', function () {
    $user = User::factory()->create();
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'ORD-TEST-123',
        'currency' => 'USD',
        'subtotal' => 10,
        'total' => 10,
        'status' => OrderStatus::PendingPayment,
    ]);

    $service = app(BugLinkDetectionService::class);
    $links = $service->detectFromUrl('https://example.com/orders/ORD-TEST-123');

    expect($links)->toHaveCount(1)
        ->and($links[0])->toMatchArray(['type' => 'order', 'reference_id' => $order->id]);
});

it('merges links from last_pages in detectForSubmit', function () {
    $service = app(BugLinkDetectionService::class);
    $request = Request::create('/livewire/update', 'POST');

    $links = $service->detectForSubmit(
        $request,
        'https://example.com/dashboard',
        [
            ['url' => 'https://example.com/admin/orders/99', 'timestamp' => now()->toIso8601String()],
        ],
        [],
    );

    expect($links)->toHaveCount(1)
        ->and($links[0])->toMatchArray(['type' => 'order', 'reference_id' => 99]);
});
