<?php

declare(strict_types=1);

namespace App\Actions\UserProductPrices;

use App\Models\User;
use App\Models\UserProductPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateUserProductPrice
{
    /**
     * @param  array{price: float|int|string, note?: string|null}  $data
     */
    public function handle(int $id, User $targetUser, array $data, User $admin): UserProductPrice
    {
        Gate::forUser($admin)->authorize('manage_user_prices');

        $row = UserProductPrice::query()
            ->whereKey($id)
            ->where('user_id', $targetUser->id)
            ->firstOrFail();

        $oldPrice = (float) $row->price;

        $validator = Validator::make($data, [
            'price' => ['required', 'numeric'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        /** @var array{price: float|int|string, note?: string|null} $validated */
        $validated = $validator->validated();

        return DB::transaction(function () use ($row, $validated, $targetUser, $admin, $oldPrice): UserProductPrice {
            $row->update([
                'price' => $validated['price'],
                'note' => $validated['note'] ?? null,
            ]);
            $row->refresh();

            activity()
                ->inLog('user_prices')
                ->event('user_product_price.updated')
                ->performedOn($targetUser)
                ->causedBy($admin)
                ->withProperties([
                    'user_id' => $targetUser->id,
                    'product_id' => $row->product_id,
                    'old_price' => $oldPrice,
                    'new_price' => (float) $row->price,
                    'admin_id' => $admin->id,
                ])
                ->log('User product price updated');

            return $row;
        });
    }
}
