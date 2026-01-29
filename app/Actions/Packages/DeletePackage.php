<?php

namespace App\Actions\Packages;

use App\Models\Package;
use App\Models\User;

class DeletePackage
{
    public function handle(int $packageId, int $adminId): void
    {
        $package = Package::query()->findOrFail($packageId);
        $package->delete();

        activity()
            ->inLog('admin')
            ->event('package.deleted')
            ->performedOn($package)
            ->causedBy(User::query()->find($adminId))
            ->withProperties([
                'package_id' => $package->id,
                'name' => $package->name,
                'category_id' => $package->category_id,
            ])
            ->log('Package deleted');
    }
}
