<?php

namespace App\Actions\Packages;

use App\Models\PackageRequirement;

class GetPackageRequirementDetails
{
    public function handle(int $requirementId): PackageRequirement
    {
        return PackageRequirement::query()->findOrFail($requirementId);
    }
}
