<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOrderFromCartPayload
{
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
                $quantity = (int) ($item['quantity'] ?? 0);
                $requirements = $item['requirements'] ?? $item['requirements_payload'] ?? null;

                if ($productId <= 0 || $quantity <= 0) {
                    throw ValidationException::withMessages([
                        "items.$index" => 'Each item must include a valid product_id and quantity.',
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

                $requiredKeys = $product->package?->requirements
                    ->where('is_required', true)
                    ->pluck('key')
                    ->all() ?? [];

                if ($requiredKeys !== []) {
                    $requirements = $item['requirements'] ?? [];
                    $missing = collect($requiredKeys)
                        ->filter(fn (string $key) => ! array_key_exists($key, $requirements) || $requirements[$key] === null || $requirements[$key] === '')
                        ->values()
                        ->all();

                    if ($missing !== []) {
                        throw ValidationException::withMessages([
                            "items.$index.requirements" => 'Missing required fields: '.implode(', ', $missing),
                        ]);
                    }
                }

                $unitPrice = (float) $product->retail_price;
                $lineTotal = round($unitPrice * $item['quantity'], 2);

                $lineItems[] = [
                    'product_id' => $product->id,
                    'package_id' => $product->package_id,
                    'name' => $product->name,
                    'unit_price' => $unitPrice,
                    'quantity' => $item['quantity'],
                    'line_total' => $lineTotal,
                    'requirements_payload' => $item['requirements'],
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

            return $order->load('items');
        };

        return $useTransaction
            ? DB::transaction($operation)
            : $operation();
    }
}
