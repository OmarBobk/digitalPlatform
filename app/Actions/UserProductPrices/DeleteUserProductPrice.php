<?php

declare(strict_types=1);

namespace App\Actions\UserProductPrices;

use App\Models\User;
use App\Models\UserProductPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeleteUserProductPrice
{
    public function handle(int $id, User $targetUser, User $admin): void
    {
        Gate::forUser($admin)->authorize('manage_user_prices');

        $row = UserProductPrice::query()
            ->whereKey($id)
            ->where('user_id', $targetUser->id)
            ->firstOrFail();

        $oldPrice = (float) $row->price;
        $productId = $row->product_id;

        DB::transaction(function () use ($row, $targetUser, $admin, $oldPrice, $productId): void {
            $row->delete();

            activity()
                ->inLog('user_prices')
                ->event('user_product_price.deleted')
                ->performedOn($targetUser)
                ->causedBy($admin)
                ->withProperties([
                    'user_id' => $targetUser->id,
                    'product_id' => $productId,
                    'old_price' => $oldPrice,
                    'new_price' => null,
                    'admin_id' => $admin->id,
                ])
                ->log('User product price deleted');
        });
    }
}
