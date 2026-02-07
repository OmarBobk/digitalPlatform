<?php

namespace App\Actions\Products;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class GetProducts
{
    public function handle(
        string $search,
        string $statusFilter,
        string $sortBy,
        string $sortDirection,
        int $perPage
    ): LengthAwarePaginator {
        $search = trim($search);

        $query = Product::query()
            ->select([
                'id',
                'package_id',
                'serial',
                'name',
                'slug',
                'entry_price',
                'retail_price',
                'wholesale_price',
                'is_active',
                'order',
                'created_at',
            ]);

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';

                $query->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhere('serial', 'like', $like);
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
            'entry_price' => 'entry_price',
            default => 'order',
        };

        $sortDirection = $sortDirection === 'desc' ? 'desc' : 'asc';

        return $query
            ->with('package:id,name,slug')
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage);
    }
}
