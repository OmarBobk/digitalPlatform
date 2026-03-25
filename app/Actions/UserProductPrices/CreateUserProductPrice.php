<?php

declare(strict_types=1);

namespace App\Actions\UserProductPrices;

use App\Models\User;
use App\Models\UserProductPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateUserProductPrice
{
    /**
     * @param  array{product_id: int, price: float|int|string, note?: string|null}  $data
     */
    public function handle(User $targetUser, array $data, User $admin): UserProductPrice
    {
        Gate::forUser($admin)->authorize('manage_user_prices');

        $validator = Validator::make($data, [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id'),
                Rule::unique('user_product_prices', 'product_id')->where(
                    fn ($query) => $query->where('user_id', $targetUser->id)
                ),
            ],
            'price' => ['required', 'numeric'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        /** @var array{product_id: int, price: float|int|string, note?: string|null} $validated */
        $validated = $validator->validated();

        return DB::transaction(function () use ($targetUser, $validated, $admin): UserProductPrice {
            $row = UserProductPrice::query()->create([
                'user_id' => $targetUser->id,
                'product_id' => $validated['product_id'],
                'price' => $validated['price'],
                'note' => $validated['note'] ?? null,
                'created_by' => $admin->id,
            ]);

            activity()
                ->inLog('user_prices')
                ->event('user_product_price.created')
                ->performedOn($targetUser)
                ->causedBy($admin)
                ->withProperties([
                    'user_id' => $targetUser->id,
                    'product_id' => $row->product_id,
                    'old_price' => null,
                    'new_price' => (float) $row->price,
                    'admin_id' => $admin->id,
                ])
                ->log('User product price created');

            return $row;
        });
    }
}
