<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    /**
     * Record a payment (down_payment or final_payment) for an order.
     */
    public function store(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'payment_type' => ['required', Rule::in(['down_payment', 'final_payment'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in(['cash', 'gcash'])],
            'reference_number' => ['required_if:payment_method,gcash', 'nullable', 'string', 'max:100'],
        ]);

        $user = Auth::user();

        if ($validated['payment_type'] === 'down_payment') {
            $this->orderService->recordDownPayment(
                $order,
                (float) $validated['amount'],
                $validated['payment_method'],
                $validated['reference_number'] ?? null,
                $user
            );
            $message = "50% down payment of ₱" . number_format($validated['amount'], 2) . " recorded. Order is now Confirmed!";
        } else {
            $this->orderService->recordFinalPayment(
                $order,
                (float) $validated['amount'],
                $validated['payment_method'],
                $validated['reference_number'] ?? null,
                $user
            );
            $message = "Final payment of ₱" . number_format($validated['amount'], 2) . " recorded. Balance settled!";
        }

        return back()->with('success', $message);
    }
}
