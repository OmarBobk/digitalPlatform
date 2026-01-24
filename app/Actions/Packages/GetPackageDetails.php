<?php

namespace App\Actions\Packages;

use App\Models\Package;

class GetPackageDetails
{
    public function handle(?int $packageId): ?Package
    {
        if ($packageId === null) {
            return null;
        }

        return Package::query()
            ->select(['id', 'category_id', 'name', 'slug', 'description', 'is_active', 'order', 'icon', 'image'])
            ->with('category:id,name,slug')
            ->find($packageId);
    }
}
