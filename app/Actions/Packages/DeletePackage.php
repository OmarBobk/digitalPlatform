<?php

namespace App\Actions\Packages;

use App\Models\Package;

class DeletePackage
{
    public function handle(int $packageId): void
    {
        Package::query()->findOrFail($packageId)->delete();
    }
}
