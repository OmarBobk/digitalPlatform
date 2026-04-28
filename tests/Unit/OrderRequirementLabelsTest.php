<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

use App\Models\OrderItem;
use App\Models\Package;
use App\Models\PackageRequirement;
use App\Support\OrderRequirementLabels;
use Illuminate\Database\Eloquent\Collection;

test('uses package requirement label when requirements are loaded', function () {
    $package = new Package;
    $requirement = new PackageRequirement([
        'key' => 'id',
        'label' => 'Epic account ID',
    ]);
    $package->setRelation('requirements', new Collection([$requirement]));

    $item = new OrderItem;
    $item->setRelation('package', $package);

    expect(OrderRequirementLabels::labelForKey($item, 'id'))->toBe('Epic account ID');
});

test('falls back when package has no matching requirement key', function () {
    $package = new Package;
    $package->setRelation('requirements', new Collection);

    $item = new OrderItem;
    $item->setRelation('package', $package);

    expect(OrderRequirementLabels::labelForKey($item, 'id'))->toBe(__('messages.requirement_label_id'));
});

test('falls back when package relation is not loaded on order item', function () {
    $item = new OrderItem;

    expect(OrderRequirementLabels::labelForKey($item, 'id'))->toBe(__('messages.requirement_label_id'));
});
