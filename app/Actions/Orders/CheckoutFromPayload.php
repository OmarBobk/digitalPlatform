<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutFromPayload
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $meta
     */
    public function handle(User $user, array $items, array $meta = [], bool $useTransaction = true): Order
    {
        if ($items === [] || array_is_list($items) === false) {
            throw ValidationException::withMessages([
                'items' => 'Cart payload is empty.',
            ]);
        }

        $wallet = Wallet::forUser($user);
        $cartHash = $this->cartHash($items);

        $operation = function () use ($user, $wallet, $items, $meta, $cartHash): Order {
            $lockedWallet = Wallet::query()
                ->whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existingOrder = Order::query()
                ->where('user_id', $user->id)
                ->whereIn('status', [OrderStatus::PendingPayment, OrderStatus::Paid])
                ->latest('id')
                ->limit(5)
                ->get()
                ->first(fn (Order $order) => data_get($order->meta, 'cart_hash') === $cartHash);

            if ($existingOrder !== null) {
                if ($existingOrder->status === OrderStatus::Paid) {
                    return $existingOrder;
                }

                return app(PayOrderWithWallet::class)->handle($existingOrder, $lockedWallet, false);
            }

            $order = app(CreateOrderFromCartPayload::class)->handle(
                $user,
                $items,
                array_merge($meta, ['cart_hash' => $cartHash]),
                false
            );

            return app(PayOrderWithWallet::class)->handle($order, $lockedWallet, false);
        };

        return $useTransaction
            ? DB::transaction($operation)
            : $operation();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function cartHash(array $items): string
    {
        $normalized = collect($items)
            ->filter(fn (mixed $item) => is_array($item))
            ->map(function (array $item): array {
                return [
                    'product_id' => $item['product_id'] ?? $item['id'] ?? null,
                    'package_id' => $item['package_id'] ?? null,
                    'quantity' => $item['quantity'] ?? null,
                    'requirements' => $item['requirements'] ?? $item['requirements_payload'] ?? null,
                ];
            })
            ->sortBy(fn (array $item) => [$item['product_id'], $item['package_id']])
            ->values()
            ->all();

        return hash('sha256', json_encode($normalized));
    }
}
