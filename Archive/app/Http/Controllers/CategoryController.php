<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Services\ResponseService;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;

class CategoryController extends Controller
{
    public function index()
    {
        return ResponseService::success(Category::all(), 'Categories fetched successfully');
    }

    public function store(StoreCategoryRequest $request)
    {
        $category = Category::create($request->validated());
        return ResponseService::success($category, 'Category created successfully', 201);
    }

    public function show(Category $category)
    {
        return ResponseService::success($category, 'Category fetched successfully');
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $category->update($request->validated());
        return ResponseService::success($category, 'Category updated successfully');
    }

    public function destroy(Request $request, Category $category)
    {
        if ($request->user()->role !== 'admin') {
            return ResponseService::error('Unauthorized', 403);
        }

        $category->delete();
        return ResponseService::success(null, 'Category deleted successfully');
    }
}
