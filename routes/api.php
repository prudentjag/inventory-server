<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('register', [AuthController::class, 'register']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::get('/users', [AuthController::class, 'users']);

    // Units (Admin Managed)
    Route::middleware(['role:admin'])->group(function () {
        Route::apiResource('units', UnitController::class);
        Route::post('units/{unit}/users', [UnitController::class, 'assignUser']);
        Route::delete('units/{unit}/users', [UnitController::class, 'removeUser']);
    });

    // Products
    // Products & Metadata
    Route::apiResource('products', ProductController::class);
    Route::apiResource('brands', BrandController::class);
    Route::apiResource('categories', CategoryController::class);

    // Inventory
    Route::middleware(['unit_access'])->group(function () {
        Route::get('/inventory', [InventoryController::class, 'index']);
        Route::post('/inventory', [InventoryController::class, 'store']); // Add/Update stock
        Route::post('/inventory/transfer', [InventoryController::class, 'transfer']);

        // Sales
        Route::post('/sales', [SalesController::class, 'store']);
        Route::get('/sales/history/{unit_id}', [SalesController::class, 'history']);
    });

    // Dashboard
    Route::get('/dashboard/stats', [\App\Http\Controllers\DashboardController::class, 'index']);
});