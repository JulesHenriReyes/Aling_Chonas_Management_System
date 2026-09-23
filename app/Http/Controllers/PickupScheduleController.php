<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PickupScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'pickup_date' => ['nullable', 'date'],
        ]);

        $orders = Order::with(['customer', 'orderDetails.product'])
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->when(
                $validated['pickup_date'] ?? null,
                fn ($query, $date) => $query->whereDate('pickup_date', $date),
                fn ($query) => $query->whereDate('pickup_date', '>=', today())
            )
            ->orderBy('pickup_date')
            ->orderBy('pickup_time')
            ->get()
            ->groupBy(fn (Order $order) => $order->pickup_date->toDateString());

        return view('admin.schedule.index', compact('orders'));
    }
}
