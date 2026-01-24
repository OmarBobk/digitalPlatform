<?php

namespace App\Actions\Categories;

use App\Models\Category;

class ToggleCategoryStatus
{
    public function handle(int $categoryId): void
    {
        $category = Category::query()->findOrFail($categoryId);

        $category->update([
            'is_active' => ! $category->is_active,
        ]);
    }
}
