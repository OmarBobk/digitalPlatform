<?php

namespace App\Actions\Products;

use App\Models\Package;
use Illuminate\Support\Collection;

class GetProductPackages
{
    public function handle(): Collection
    {
        return Package::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->limit(200)
            ->get();
    }
}
