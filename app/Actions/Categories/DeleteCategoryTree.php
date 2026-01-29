<?php

namespace App\Actions\Categories;

use App\Models\Category;
use App\Models\User;

class DeleteCategoryTree
{
    public function handle(int $categoryId, int $adminId): void
    {
        $root = Category::query()->findOrFail($categoryId);
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

        activity()
            ->inLog('admin')
            ->event('category.deleted')
            ->performedOn($root)
            ->causedBy(User::query()->find($adminId))
            ->withProperties([
                'root_category_id' => $root->id,
                'deleted_count' => count($ids),
                'deleted_ids' => array_slice($ids, 0, 20),
            ])
            ->log('Category deleted');
    }
}
