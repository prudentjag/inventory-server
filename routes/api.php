<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\SalesController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);

    // Products
    Route::apiResource('products', ProductController::class);

    // Inventory
    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::post('/inventory', [InventoryController::class, 'store']); // Add/Update stock
    Route::post('/inventory/transfer', [InventoryController::class, 'transfer']);

    // Sales
    Route::post('/sales', [SalesController::class, 'store']);
    Route::get('/sales/history/{unit_id}', [SalesController::class, 'history']);
});