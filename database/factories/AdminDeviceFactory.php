<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AdminDevice>
 */
class AdminDeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'fcm_token' => 'fcm-'.fake()->unique()->uuid(),
            'device_name' => fake()->optional(0.8)->slug(2),
            'last_seen_at' => now(),
        ];
    }
}
