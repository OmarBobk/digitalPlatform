<?php

namespace App\Actions\Categories;

use App\Models\Category;

class DeleteCategoryTree
{
    public function handle(int $categoryId): void
    {
        $ids = [$categoryId];
        $queue = [$categoryId];

        while ($queue !== []) {
            $children = Category::query()
                ->whereIn('parent_id', $queue)
                ->pluck('id')
                ->all();

            if ($children === []) {
                break;
            }

            $ids = array_merge($ids, $children);
            $queue = $children;
        }

        Category::query()
            ->whereIn('id', $ids)
            ->delete();
    }
}
