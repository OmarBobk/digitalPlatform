<?php

namespace Database\Seeders;

use App\Models\LoyaltyTierConfig;
use Illuminate\Database\Seeder;

class LoyaltyTierConfigSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'customer' => [
                ['name' => 'bronze', 'min_spend' => 0, 'discount_percentage' => 0],
                ['name' => 'silver', 'min_spend' => 500, 'discount_percentage' => 5],
                ['name' => 'gold', 'min_spend' => 2000, 'discount_percentage' => 10],
            ],
            'salesperson' => [
                ['name' => 'bronze', 'min_spend' => 0, 'discount_percentage' => 0],
                ['name' => 'silver', 'min_spend' => 300, 'discount_percentage' => 5],
                ['name' => 'gold', 'min_spend' => 1000, 'discount_percentage' => 10],
            ],
        ];

        foreach ($roles as $role => $tiers) {
            foreach ($tiers as $tier) {
                LoyaltyTierConfig::query()->updateOrCreate(
                    ['role' => $role, 'name' => $tier['name']],
                    ['min_spend' => $tier['min_spend'], 'discount_percentage' => $tier['discount_percentage']]
                );
            }
        }
    }
}
