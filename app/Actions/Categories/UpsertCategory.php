<?php

namespace App\Actions\Categories;

use App\Models\Category;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class UpsertCategory
{
    /**
     * @param  array{parent_id:?int,name:string,order:int,icon:?string,is_active:bool}  $data
     */
    public function handle(?int $categoryId, array $data, ?UploadedFile $imageFile): Category
    {
        $category = $categoryId !== null
            ? Category::query()->findOrFail($categoryId)
            : new Category;

        $imagePath = null;

        if ($imageFile !== null) {
            $directory = public_path('images/categories');

            File::ensureDirectoryExists($directory);

            $extension = $imageFile->getClientOriginalExtension();

            if ($extension === '') {
                $extension = $imageFile->guessExtension() ?? 'jpg';
            }

            $filename = Str::uuid().'.'.$extension;

            $imagePath = 'images/categories/'.$filename;
            $destination = public_path($imagePath);

            File::copy($imageFile->getRealPath(), $destination);
        }

        $category->fill([
            'parent_id' => $data['parent_id'],
            'name' => trim($data['name']),
            'order' => $data['order'],
            'icon' => $data['icon'],
            'is_active' => $data['is_active'],
            'image' => $imagePath ?? $category->image,
        ]);

        $category->save();

        return $category;
    }
}
