<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\StockRequest;
use App\Models\FacilityBooking;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            $today = now()->toDateString();
            return response()->json([
                'role' => 'admin',
                'total_users' => User::count(),
                'total_products' => Product::count(),
                'total_sales_count' => Sale::where('payment_status', 'paid')->count(),
                'total_revenue' => Sale::where('payment_status', 'paid')->sum('total_amount'),
                'low_stock_alerts' => Inventory::whereColumn('quantity', '<=', 'low_stock_threshold')->count(),
                // Facility booking stats
                'bookings_today' => FacilityBooking::where('booking_date', $today)->where('status', '!=', 'cancelled')->count(),
                'bookings_revenue_today' => FacilityBooking::where('booking_date', $today)->where('status', 'confirmed')->sum('total_amount'),
            ]);
        }

        if ($user->role === 'stockist') {
            return response()->json([
                'role' => 'stockist',
                'total_central_stock' => Stock::sum('quantity'),
                'total_products_in_stock' => Stock::count(),
                'low_stock_alerts' => Stock::whereColumn('quantity', '<=', 'low_stock_threshold')->count(),
                'pending_requests' => StockRequest::where('status', 'pending')->count(),
            ]);
        }

        if (in_array($user->role, ['manager', 'unit_head'])) {
            $unitIds = $user->units()->pluck('units.id');
            $today = now()->toDateString();

            return response()->json([
                'role' => $user->role,
                'unit_sales_count' => Sale::whereIn('unit_id', $unitIds)->where('payment_status', 'paid')->count(),
                'unit_revenue' => Sale::whereIn('unit_id', $unitIds)->where('payment_status', 'paid')->sum('total_amount'),
                'low_stock_alerts' => Inventory::whereIn('unit_id', $unitIds)
                    ->whereColumn('quantity', '<=', 'low_stock_threshold')
                    ->count(),
                'total_products_in_units' => Inventory::whereIn('unit_id', $unitIds)
                    ->distinct('product_id')
                    ->count('product_id'),
                // Facility booking stats for unit
                'unit_bookings_today' => FacilityBooking::whereHas('facility', fn($q) => $q->whereIn('unit_id', $unitIds))
                    ->where('booking_date', $today)
                    ->where('status', '!=', 'cancelled')
                    ->count(),
            ]);
        }

        // Default to user/staff stats
        return response()->json([
            'role' => 'staff',
            'my_sales_count' => Sale::where('user_id', $user->id)->where('payment_status', 'paid')->count(),
            'my_revenue' => Sale::where('user_id', $user->id)->where('payment_status', 'paid')->sum('total_amount'),
            // simple count of items sold by this user
            'items_sold' => DB::table('sales')
                ->join('sale_items', 'sales.id', '=', 'sale_items.sale_id')
                ->where('sales.user_id', $user->id)
                ->where('sales.payment_status', 'paid')
                ->sum('sale_items.quantity'),
        ]);
    }
}
