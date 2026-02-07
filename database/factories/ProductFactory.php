<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true);
        $serial = fake()->boolean(70)
            ? fake()->unique()->bothify('SER-#####')
            : null;

        $entryPrice = fake()->randomFloat(2, 5, 500);

        return [
            'package_id' => Package::factory(),
            'serial' => $serial,
            'name' => $name,
            'slug' => Str::slug($name),
            'entry_price' => $entryPrice,
            'is_active' => fake()->boolean(80),
            'order' => fake()->unique()->numberBetween(1, 100),
        ];
    }
}
