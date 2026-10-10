<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\PickupCalendar;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PickupScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'pickup_date' => ['nullable', 'date'],
        ]);

        $baseQuery = Order::with(['customer', 'orderDetails.product', 'orderDetails.addOns', 'payments', 'paymentProofs'])
            ->whereNotIn('status', ['completed', 'cancelled']);

        $capacityOrders = (clone $baseQuery)
            ->whereDate('pickup_date', '>=', PickupCalendar::todayString())
            ->orderBy('pickup_date')
            ->orderBy('pickup_time')
            ->get()
            ->groupBy(fn (Order $order) => $order->pickup_date->toDateString());

        $orders = (clone $baseQuery)
            ->when(
                $validated['pickup_date'] ?? null,
                fn ($query, $date) => $query->whereDate('pickup_date', $date),
                fn ($query) => $query->whereDate('pickup_date', '>=', PickupCalendar::todayString())
            )
            ->orderBy('pickup_date')
            ->orderBy('pickup_time')
            ->get()
            ->groupBy(fn (Order $order) => $order->pickup_date->toDateString());

        return view('admin.schedule.index', compact('orders', 'capacityOrders'));
    }
}
