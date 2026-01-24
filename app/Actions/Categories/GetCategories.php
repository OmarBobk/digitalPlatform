<?php

namespace App\Actions\Categories;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class GetCategories
{
    public function handle(
        string $search,
        string $statusFilter,
        string $sortBy,
        string $sortDirection,
        int $perPage
    ): LengthAwarePaginator {
        $search = trim($search);

        $query = Category::query()
            ->select([
                'id',
                'parent_id',
                'name',
                'slug',
                'order',
                'icon',
                'is_active',
                'image',
                'created_at',
            ]);

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';

                $query->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like);
            });
        }

        if ($statusFilter === 'active') {
            $query->where('is_active', true);
        }

        if ($statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        $sortColumn = match ($sortBy) {
            'name' => 'name',
            'created_at' => 'created_at',
            default => 'order',
        };

        $sortDirection = $sortDirection === 'desc' ? 'desc' : 'asc';

        return $query
            ->with('parent:id,name,slug')
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage);
    }
}
