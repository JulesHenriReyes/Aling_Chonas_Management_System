<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Supply;
use App\Support\PickupCalendar;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display internal staff dashboard.
     */
    public function index(): View
    {
        $pendingCount = Order::workflowQueue('review')->count();
        $activeOrdersCount = Order::workflowQueue('booked')->count();
        $awaitingDepositCount = Order::workflowQueue('deposit')->count();
        $awaitingReceiptCount = Order::workflowQueue('receipts')->count();
        $completedCount = Order::where('status', 'completed')->count();

        // Low stock: current_quantity <= reorder_level
        $lowStockCount = Supply::where('is_active', true)
            ->whereColumn('current_quantity', '<=', 'reorder_level')
            ->count();

        // Pickups today
        $todayPickups = Order::with(['customer', 'payments', 'paymentProofs'])
            ->whereDate('pickup_date', PickupCalendar::todayString())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderBy('pickup_time')
            ->get();

        // Recent orders (last 10)
        $recentOrders = Order::with(['customer', 'user', 'orderDetails.product', 'payments', 'paymentProofs'])
            ->latest()
            ->take(10)
            ->get();

        return view('admin.dashboard', compact(
            'pendingCount',
            'activeOrdersCount',
            'awaitingDepositCount',
            'awaitingReceiptCount',
            'completedCount',
            'lowStockCount',
            'todayPickups',
            'recentOrders'
        ));
    }
}
