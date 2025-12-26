<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;
use App\Http\Services\ResponseService;

use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;

class BrandController extends Controller
{
    public function index()
    {
        return ResponseService::success(Brand::all(), 'Brands fetched successfully');
    }

    public function store(StoreBrandRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('brands', 'public');
            $data['image_path'] = $path;
        }

        $brand = Brand::create($data);
        return ResponseService::success($brand, 'Brand created successfully', 201);
    }

    public function show(Brand $brand)
    {
        return ResponseService::success($brand, 'Brand fetched successfully');
    }

    public function update(UpdateBrandRequest $request, Brand $brand)
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($brand->image_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($brand->image_path);
            }
            $path = $request->file('image')->store('brands', 'public');
            $data['image_path'] = $path;
        }

        $brand->update($data);
        return ResponseService::success($brand, 'Brand updated successfully');
    }

    public function destroy(Request $request, Brand $brand)
    {
        if ($request->user()->role !== 'admin') {
            return ResponseService::error('Unauthorized', 403);
        }

        $brand->delete();
        return ResponseService::success(null, 'Brand deleted successfully');
    }
}
