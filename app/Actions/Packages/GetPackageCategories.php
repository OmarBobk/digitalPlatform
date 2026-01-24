<?php

namespace App\Actions\Packages;

use App\Models\Category;
use Illuminate\Support\Collection;

class GetPackageCategories
{
    public function handle(): Collection
    {
        return Category::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->limit(200)
            ->get();
    }
}
