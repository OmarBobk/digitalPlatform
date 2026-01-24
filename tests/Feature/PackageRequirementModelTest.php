<?php

use App\Models\Package;
use App\Models\PackageRequirement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('package requirements table has expected columns', function () {
    expect(Schema::hasColumns('package_requirements', [
        'id',
        'package_id',
        'key',
        'label',
        'type',
        'is_required',
        'validation_rules',
        'order',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('package requirement belongs to package', function () {
    $package = Package::factory()->create();
    $requirement = PackageRequirement::factory()->create(['package_id' => $package->id]);

    expect($requirement->package)->not->toBeNull();
    expect($requirement->package->is($package))->toBeTrue();
});
