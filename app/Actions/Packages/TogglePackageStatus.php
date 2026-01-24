<?php

namespace App\Actions\Packages;

use App\Models\Package;

class TogglePackageStatus
{
    public function handle(int $packageId): void
    {
        $package = Package::query()->findOrFail($packageId);

        $package->update([
            'is_active' => ! $package->is_active,
        ]);
    }
}
