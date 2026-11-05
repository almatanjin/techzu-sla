<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListCategoriesRequest;
use App\Http\Resources\CategoryResource;
use App\Services\CategoryService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categoryService,
    ) {}

    public function index(ListCategoriesRequest $request): AnonymousResourceCollection
    {
        $categories = $this->categoryService->list(
            $request->user(),
            $request->includeInactive()
        );

        return CategoryResource::collection($categories);
    }
}

