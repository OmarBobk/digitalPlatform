<?php

namespace App\Actions\Packages;

use App\Models\PackageRequirement;

class DeletePackageRequirement
{
    public function handle(int $requirementId): void
    {
        PackageRequirement::query()->findOrFail($requirementId)->delete();
    }
}
