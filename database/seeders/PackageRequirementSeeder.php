<?php

namespace Database\Seeders;

use App\Models\PackageRequirement;
use Illuminate\Database\Seeder;

class PackageRequirementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PackageRequirement::factory()->count(12)->create();
    }
}
