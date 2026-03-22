<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use App\Models\UserProductPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserProductPrice>
 */
class UserProductPriceFactory extends Factory
{
    protected $model = UserProductPrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'price' => fake()->randomFloat(2, 1, 999),
            'note' => fake()->optional()->sentence(),
            'created_by' => null,
        ];
    }
}
