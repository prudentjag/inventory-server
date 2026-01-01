<?php

namespace App\Http\Controllers;

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
            return response()->json([
                'role' => 'admin',
                'total_users' => User::count(),
                'total_products' => Product::count(),
                'total_sales_count' => Sale::count(),
                'total_revenue' => Sale::sum('total_amount'),
                'low_stock_alerts' => Inventory::whereColumn('quantity', '<=', 'low_stock_threshold')->count(),
            ]);
        }

        if ($user->role === 'stockist') {
            return response()->json([
                'role' => 'stockist',
                'total_central_stock' => \App\Models\Stock::sum('quantity'),
                'total_products_in_stock' => \App\Models\Stock::count(),
                'low_stock_alerts' => \App\Models\Stock::whereColumn('quantity', '<=', 'low_stock_threshold')->count(),
                'pending_requests' => \App\Models\StockRequest::where('status', 'pending')->count(),
            ]);
        }

        if (in_array($user->role, ['manager', 'unit_head'])) {
            $unitIds = $user->units()->pluck('units.id');

            return response()->json([
                'role' => $user->role,
                'unit_sales_count' => Sale::whereIn('unit_id', $unitIds)->count(),
                'unit_revenue' => Sale::whereIn('unit_id', $unitIds)->sum('total_amount'),
                'low_stock_alerts' => Inventory::whereIn('unit_id', $unitIds)
                    ->whereColumn('quantity', '<=', 'low_stock_threshold')
                    ->count(),
                'total_products_in_units' => Inventory::whereIn('unit_id', $unitIds)
                    ->distinct('product_id')
                    ->count('product_id'),
            ]);
        }

        // Default to user/staff stats
        return response()->json([
            'role' => 'staff',
            'my_sales_count' => Sale::where('user_id', $user->id)->count(),
            'my_revenue' => Sale::where('user_id', $user->id)->sum('total_amount'),
            // simple count of items sold by this user
            'items_sold' => DB::table('sales')
                ->join('sale_items', 'sales.id', '=', 'sale_items.sale_id')
                ->where('sales.user_id', $user->id)
                ->sum('sale_items.quantity'),
        ]);
    }
}
