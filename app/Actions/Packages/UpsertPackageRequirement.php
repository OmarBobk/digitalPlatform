<?php

namespace App\Actions\Packages;

use App\Models\PackageRequirement;

class UpsertPackageRequirement
{
    /**
     * @param  array{key:string,label:string,type:string,is_required:bool,validation_rules:?string,order:int}  $data
     */
    public function handle(?int $requirementId, int $packageId, array $data): PackageRequirement
    {
        $requirement = $requirementId !== null
            ? PackageRequirement::query()->findOrFail($requirementId)
            : new PackageRequirement;

        $requirement->fill([
            'package_id' => $packageId,
            'key' => $data['key'],
            'label' => $data['label'],
            'type' => $data['type'],
            'is_required' => $data['is_required'],
            'validation_rules' => $data['validation_rules'],
            'order' => $data['order'],
        ]);

        $requirement->save();

        return $requirement;
    }
}
