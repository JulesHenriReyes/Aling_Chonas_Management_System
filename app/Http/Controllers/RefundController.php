<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Refund;
use App\Services\RefundService;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function store(Request $request, Order $order, RefundService $refunds)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $refunds->markBakeryFailure($order, $data['reason'], $request->user());

        return redirect()->route('orders.show', $order)->with('success', 'Bakery failure recorded. Any verified payments are due for a full refund; confirm completion after returning the money.');
    }

    public function complete(Request $request, Refund $refund, RefundService $refunds)
    {
        $refunds->complete($refund, $request->only(['method', 'reference_number', 'transfer_confirmed']), $request->user());

        return redirect()->route('orders.show', $refund->order_id)->with('success', 'Refund transfer recorded as completed.');
    }
}
