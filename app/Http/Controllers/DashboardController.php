<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Supply;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display internal staff dashboard.
     */
    public function index(): View
    {
        $pendingCount = Order::where('status', 'pending')->count();
        $activeOrdersCount = Order::whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup'])->count();
        $completedCount = Order::where('status', 'completed')->count();

        // Low stock: current_quantity <= reorder_level
        $lowStockCount = Supply::where('is_active', true)
            ->whereColumn('current_quantity', '<=', 'reorder_level')
            ->count();

        // Pickups today
        $todayPickups = Order::with('customer')
            ->whereDate('pickup_date', today())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderBy('pickup_time')
            ->get();

        // Recent orders (last 10)
        $recentOrders = Order::with(['customer', 'user', 'orderDetails.product'])
            ->latest()
            ->take(10)
            ->get();

        return view('admin.dashboard', compact(
            'pendingCount',
            'activeOrdersCount',
            'completedCount',
            'lowStockCount',
            'todayPickups',
            'recentOrders'
        ));
    }
}
