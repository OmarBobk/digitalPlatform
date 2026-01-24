<?php

namespace App\Actions\Packages;

use App\Models\PackageRequirement;
use Illuminate\Support\Collection;

class GetPackageRequirements
{
    public function handle(?int $packageId): Collection
    {
        if ($packageId === null) {
            return collect();
        }

        return PackageRequirement::query()
            ->select([
                'id',
                'package_id',
                'key',
                'label',
                'type',
                'is_required',
                'validation_rules',
                'order',
            ])
            ->where('package_id', $packageId)
            ->orderBy('order')
            ->get();
    }
}
