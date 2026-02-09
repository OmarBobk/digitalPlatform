<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Packages\ResolvePackageRequirements;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CustomerPriceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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

                $requirements = $item['requirements'] ?? [];
                $this->validateRequirements($product->package?->requirements ?? collect(), $requirements, $index);

                $unitPrice = app(CustomerPriceService::class)->finalPrice($product, $user);
                $lineTotal = round($unitPrice * $item['quantity'], 2);
                $entryPrice = $product->entry_price !== null ? (float) $product->entry_price : null;

                $lineItems[] = [
                    'product_id' => $product->id,
                    'package_id' => $product->package_id,
                    'name' => $product->name,
                    'unit_price' => $unitPrice,
                    'entry_price' => $entryPrice,
                    'quantity' => $item['quantity'],
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
}
