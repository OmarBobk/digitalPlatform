<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PackageRequirement>
 */
class PackageRequirementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = fake()->randomElement(['id', 'username', 'phone']);
        $labels = [
            'id' => 'ID',
            'username' => 'Username',
            'phone' => 'Phone',
        ];
        $type = fake()->randomElement(['string', 'number', 'select']);
        $isRequired = fake()->boolean(70);

        $rules = [];
        if ($isRequired) {
            $rules[] = 'required';
        }
        if ($type === 'number') {
            $rules[] = 'numeric';
        }
        if ($type === 'string') {
            $rules[] = 'string';
        }
        if ($type === 'select') {
            $rules[] = 'in:option_one,option_two';
        }

        return [
            'package_id' => Package::factory(),
            'key' => $key,
            'label' => $labels[$key],
            'type' => $type,
            'is_required' => $isRequired,
            'validation_rules' => $rules === [] ? null : implode('|', $rules),
            'order' => fake()->numberBetween(1, 100),
        ];
    }
}
