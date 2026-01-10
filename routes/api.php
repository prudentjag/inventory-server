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
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockRequestController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\PaymentWebhookController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/webhooks/monnify', [PaymentWebhookController::class, 'handleMonnify']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('register', [AuthController::class, 'register']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::get('/users', [AuthController::class, 'users']);

    // Units (Admin Managed)
    Route::middleware(['role:admin,stockist,staff'])->group(function () {
        Route::apiResource('units', UnitController::class);
        Route::post('units/{unit}/users', [UnitController::class, 'assignUser']);
        Route::delete('units/{unit}/users', [UnitController::class, 'removeUser']);
    });

    // Products
    Route::apiResource('products', ProductController::class);

    // Brands & Categories (Admin/Stockist only)
    Route::middleware(['role:admin,stockist,staff'])->group(function () {
        Route::apiResource('brands', BrandController::class);
        Route::apiResource('categories', CategoryController::class);
    });

    // Inventory
    Route::middleware(['unit_access'])->group(function () {
        Route::get('/inventory', [InventoryController::class, 'index']);
        Route::post('/inventory', [InventoryController::class, 'store']); // Add/Update stock
        Route::post('/inventory/transfer', [InventoryController::class, 'transfer']);

        // Sales
        Route::post('/sales', [SalesController::class, 'store']);
        Route::get('/sales/history/{unit_id}', [SalesController::class, 'history']);
        Route::get('/sales/{invoice_number}/verify-payment', [SalesController::class, 'verifyPayment']);
        Route::get('/my-sales', [SalesController::class, 'mySales']);
    });

    // Dashboard
    Route::get('/dashboard/stats', [\App\Http\Controllers\DashboardController::class, 'index']);

    // Central Stock & Global Sales (Stockist/Admin only)
    Route::middleware(['role:admin,stockist'])->group(function () {
        Route::apiResource('stock', StockController::class);
        Route::get('/sales', [SalesController::class, 'index']);
    });

    // Stock Requests
    Route::get('/stock-requests', [StockRequestController::class, 'index']);
    Route::post('/stock-requests', [StockRequestController::class, 'store']);
    Route::get('/stock-requests/{stockRequest}', [StockRequestController::class, 'show']);
    Route::post('/stock-requests/{stockRequest}/approve', [StockRequestController::class, 'approve']);
    Route::post('/stock-requests/{stockRequest}/reject', [StockRequestController::class, 'reject']);

    // Audit Logs (Admin/Stockist only)
    Route::middleware(['role:admin,stockist'])->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
        Route::get('/audit-logs/{type}/{id}', [AuditLogController::class, 'resourceTrail']);
    });

    // Facilities (Admin/Manager can manage, all authenticated can view)
    Route::get('/facilities', [\App\Http\Controllers\FacilityController::class, 'index']);
    Route::get('/facilities/types', [\App\Http\Controllers\FacilityController::class, 'types']);
    Route::get('/facilities/{facility}', [\App\Http\Controllers\FacilityController::class, 'show']);
    Route::get('/facilities/{facility}/availability', [\App\Http\Controllers\FacilityBookingController::class, 'availability']);
    
    Route::middleware(['role:admin,manager'])->group(function () {
        Route::post('/facilities', [\App\Http\Controllers\FacilityController::class, 'store']);
        Route::put('/facilities/{facility}', [\App\Http\Controllers\FacilityController::class, 'update']);
        Route::delete('/facilities/{facility}', [\App\Http\Controllers\FacilityController::class, 'destroy']);
    });

    // Facility Bookings
    Route::apiResource('facility-bookings', \App\Http\Controllers\FacilityBookingController::class);
    Route::post('/facility-bookings/{facilityBooking}/confirm', [\App\Http\Controllers\FacilityBookingController::class, 'confirm']);
    Route::post('/facility-bookings/{facilityBooking}/cancel', [\App\Http\Controllers\FacilityBookingController::class, 'cancel']);

    // Facility Tickets (drop-in / individual payments)
    Route::apiResource('facility-tickets', \App\Http\Controllers\FacilityTicketController::class)->except(['destroy']);
    Route::post('/facility-tickets/{facilityTicket}/refund', [\App\Http\Controllers\FacilityTicketController::class, 'refund']);
    Route::get('/facility-tickets-stats', [\App\Http\Controllers\FacilityTicketController::class, 'stats']);
});