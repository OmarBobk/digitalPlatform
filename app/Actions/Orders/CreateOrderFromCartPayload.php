<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Packages\ResolvePackageRequirements;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductAmountMode;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderPriceFlooredNotification;
use App\Services\CustomerPriceService;
use App\Services\NotificationRecipientService;
use App\Services\SystemEventService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateOrderFromCartPayload
{
    private const DEFAULT_CUSTOM_AMOUNT_HARD_CAP = 100000;

    /**
     * Create a server-side order snapshot from untrusted cart payload.
     * Fee uses config('billing.checkout_fee_fixed').
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>|null  $meta
     */
    public function handle(User $user, array $items, ?array $meta = null, bool $useTransaction = true): Order
    {
        $operation = function () use ($user, $items, $meta): Order {
            if ($items === [] || array_is_list($items) === false) {
                throw ValidationException::withMessages([
                    'items' => 'Cart payload must be a non-empty list.',
                ]);
            }

            $normalizedItems = collect($items)->map(function (mixed $item, int $index): array {
                if (! is_array($item)) {
                    throw ValidationException::withMessages([
                        "items.$index" => 'Each item must be an object.',
                    ]);
                }

                $productId = (int) ($item['product_id'] ?? $item['id'] ?? 0);
                $packageId = isset($item['package_id']) ? (int) $item['package_id'] : null;
                $quantity = isset($item['quantity']) ? (int) $item['quantity'] : null;
                $requestedAmountRaw = $item['requested_amount'] ?? null;
                $requirements = $item['requirements'] ?? $item['requirements_payload'] ?? null;

                if ($productId <= 0) {
                    throw ValidationException::withMessages([
                        "items.$index.product_id" => 'Each item must include a valid product_id.',
                    ]);
                }

                if ($requirements !== null && ! is_array($requirements)) {
                    throw ValidationException::withMessages([
                        "items.$index.requirements" => 'Requirements payload must be an object.',
                    ]);
                }

                return [
                    'product_id' => $productId,
                    'package_id' => $packageId,
                    'quantity' => $quantity,
                    'requested_amount' => $requestedAmountRaw,
                    'requirements' => $requirements,
                ];
            })->values();

            $productIds = $normalizedItems->pluck('product_id')->unique()->all();

            $products = Product::query()
                ->with('package.requirements')
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            $lineItems = [];
            $subtotal = 0.0;
            $flooredItemsCount = 0;

            $priceService = app(CustomerPriceService::class);
            $priceOverrides = $priceService->getUserOverridesFor($user);

            foreach ($normalizedItems as $index => $item) {
                $product = $products->get($item['product_id']);

                if ($product === null) {
                    throw ValidationException::withMessages([
                        "items.$index.product_id" => 'Selected product does not exist.',
                    ]);
                }

                if ($item['package_id'] !== null && $product->package_id !== $item['package_id']) {
                    throw ValidationException::withMessages([
                        "items.$index.package_id" => 'Selected package does not match product.',
                    ]);
                }

                $requirements = $item['requirements'] ?? [];
                $this->validateRequirements($product->package?->requirements ?? collect(), $requirements, $index);

                $amountMode = $product->amount_mode ?? ProductAmountMode::Fixed;
                $entryPrice = $product->entry_price !== null ? (float) $product->entry_price : null;
                $quantity = max(1, (int) ($item['quantity'] ?? 1));
                $requestedAmount = null;
                $pricingMeta = null;

                if ($amountMode === ProductAmountMode::Custom) {
                    $requestedAmount = $this->validateCustomAmount($product, $item, $index);
                    $quantity = 1;
                    if ($entryPrice === null || $entryPrice <= 0) {
                        throw ValidationException::withMessages([
                            "items.$index.product_id" => 'Invalid entry price for custom product.',
                        ]);
                    }

                    $computedEntryTotal = (float) bcmul(
                        (string) $requestedAmount,
                        number_format($entryPrice, 6, '.', ''),
                        6
                    );
                    $price = $priceService->finalPriceForAmount($product, $requestedAmount, $user, $priceOverrides);
                    $unitPrice = $price['final_price'];
                    $lineTotal = $unitPrice;
                    $pricingMeta = [
                        'mode' => ProductAmountMode::Custom->value,
                        'requested_amount' => $requestedAmount,
                        'entry_price' => $entryPrice,
                        'computed_entry_total' => $computedEntryTotal,
                    ];
                } else {
                    if ($quantity <= 0) {
                        throw ValidationException::withMessages([
                            "items.$index.quantity" => 'Fixed amount products require quantity greater than zero.',
                        ]);
                    }
                    $price = $priceService->priceFor($product, $user, $priceOverrides);
                    $unitPrice = $price['final_price'];
                    $lineTotal = round($unitPrice * $quantity, 2);
                }

                if (($price['meta']['is_floor_applied'] ?? false) === true) {
                    $flooredItemsCount++;
                }

                $lineItems[] = [
                    'product_id' => $product->id,
                    'package_id' => $product->package_id,
                    'name' => $product->name,
                    'unit_price' => $unitPrice,
                    'entry_price' => $entryPrice,
                    'quantity' => $quantity,
                    'amount_mode' => $amountMode,
                    'requested_amount' => $requestedAmount,
                    'amount_unit_label' => $product->amount_unit_label,
                    'pricing_meta' => $pricingMeta,
                    'line_total' => $lineTotal,
                    'requirements_payload' => $requirements,
                    'status' => OrderItemStatus::Pending,
                ];

                $subtotal += $lineTotal;
            }

            $fee = (float) config('billing.checkout_fee_fixed', 0);
            $total = round($subtotal + $fee, 2);

            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => Order::temporaryOrderNumber(),
                'currency' => config('billing.currency', 'USD'),
                'subtotal' => $subtotal,
                'fee' => $fee,
                'total' => $total,
                'status' => OrderStatus::PendingPayment,
                'meta' => $meta,
            ]);

            $order->order_number = Order::generateOrderNumber($order->id, $order->created_at?->year);
            $order->save();

            foreach ($lineItems as $lineItem) {
                $order->items()->create($lineItem);
            }

            foreach ($lineItems as $lineItem) {
                if (($lineItem['amount_mode'] ?? ProductAmountMode::Fixed) !== ProductAmountMode::Custom) {
                    continue;
                }

                Log::info('custom_amount_order', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'user_id' => $user->id,
                    'product_id' => $lineItem['product_id'] ?? null,
                    'amount' => $lineItem['requested_amount'] ?? null,
                    'final_price' => $lineItem['unit_price'] ?? null,
                    'currency' => $order->currency,
                ]);
            }

            activity()
                ->inLog('orders')
                ->event('order.created')
                ->performedOn($order)
                ->causedBy($user)
                ->withProperties([
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'item_count' => count($lineItems),
                    'subtotal' => $order->subtotal,
                    'fee' => $order->fee,
                    'total' => $order->total,
                    'currency' => $order->currency,
                    'status_to' => $order->status->value,
                ])
                ->log('Order created');

            $orderId = $order->id;
            $userId = $user->id;
            DB::afterCommit(function () use ($orderId, $userId): void {
                $order = Order::query()->find($orderId);
                $user = User::query()->find($userId);
                if ($order !== null && $user !== null) {
                    app(SystemEventService::class)->record(
                        'order.created',
                        $order,
                        $user,
                        [
                            'order_number' => $order->order_number,
                            'item_count' => $order->items()->count(),
                            'total' => (float) $order->total,
                            'currency' => $order->currency,
                        ],
                        'info',
                        false,
                    );
                }
            });
            if ($flooredItemsCount > 0) {
                DB::afterCommit(function () use ($orderId, $flooredItemsCount): void {
                    $createdOrder = Order::query()->find($orderId);
                    if ($createdOrder === null) {
                        return;
                    }
                    $notification = OrderPriceFlooredNotification::fromOrder($createdOrder, $flooredItemsCount);
                    app(NotificationRecipientService::class)
                        ->adminUsers()
                        ->each(fn (User $admin): mixed => $admin->notify($notification));
                });
            }

            return $order->load('items');
        };

        return $useTransaction
            ? DB::transaction($operation)
            : $operation();
    }

    /**
     * @param  Collection<int, \App\Models\PackageRequirement>  $requirements
     * @param  array<string, mixed>  $values
     */
    private function validateRequirements(Collection $requirements, array $values, int $index): void
    {
        if ($requirements->isEmpty()) {
            return;
        }

        $resolved = app(ResolvePackageRequirements::class)->handle($requirements);
        $rules = $resolved['rules'];
        $attributes = $resolved['attributes'];

        $validator = Validator::make($values, $rules, [], $attributes);

        if (! $validator->fails()) {
            return;
        }

        $messages = [];

        foreach ($validator->errors()->messages() as $field => $fieldMessages) {
            $messages["items.$index.requirements.$field"] = $fieldMessages;
        }

        throw ValidationException::withMessages($messages !== [] ? $messages : [
            "items.$index.requirements" => 'Missing or invalid requirements.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function validateCustomAmount(Product $product, array $item, int $index): int
    {
        $requestedAmountRaw = $item['requested_amount'] ?? null;
        $requestedAmount = filter_var($requestedAmountRaw, FILTER_VALIDATE_INT);
        $minimum = $product->custom_amount_min;
        $maximum = $product->custom_amount_max;
        $step = $product->custom_amount_step ?? 1;
        $hardCap = (int) config('billing.custom_amount_hard_cap', self::DEFAULT_CUSTOM_AMOUNT_HARD_CAP);

        if ($requestedAmount === false || $requestedAmount <= 0) {
            throw ValidationException::withMessages([
                "items.$index.requested_amount" => 'Requested amount must be a positive integer.',
            ]);
        }

        if ($hardCap > 0 && $requestedAmount > $hardCap) {
            throw ValidationException::withMessages([
                "items.$index.requested_amount" => "Requested amount may not be greater than {$hardCap}.",
            ]);
        }

        if ($minimum !== null && $requestedAmount < $minimum) {
            throw ValidationException::withMessages([
                "items.$index.requested_amount" => "Requested amount must be at least {$minimum}.",
            ]);
        }

        if ($maximum !== null && $requestedAmount > $maximum) {
            throw ValidationException::withMessages([
                "items.$index.requested_amount" => "Requested amount may not be greater than {$maximum}.",
            ]);
        }

        if ($step !== null && $step > 1 && $requestedAmount % $step !== 0) {
            throw ValidationException::withMessages([
                "items.$index.requested_amount" => "Requested amount must be in increments of {$step}.",
            ]);
        }

        return (int) $requestedAmount;
    }
}
