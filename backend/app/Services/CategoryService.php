<?php

namespace App\Services;

use App\Data\CreateCategoryData;
use App\Data\UpdateCategoryData;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class CategoryService
{
    /**
     * @return Collection<int, Category>
     */
    public function list(User $user, bool $includeInactive = false): Collection
    {
        $query = Category::query()->orderBy('name', 'asc');

        if (! ($user->isAdmin() && $includeInactive)) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    public function create(CreateCategoryData $data): Category
    {
        $category = new Category();
        $category->name = $data->name;
        $category->is_active = $data->isActive;
        $category->save();

        return $category;
    }

    public function update(Category $category, UpdateCategoryData $data): Category
    {
        if ($data->hasName && $data->name !== null) {
            $category->name = $data->name;
        }

        if ($data->hasIsActive && $data->isActive !== null) {
            $category->is_active = $data->isActive;
        }

        $category->save();

        return $category;
    }
}

