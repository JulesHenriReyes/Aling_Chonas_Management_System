<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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
        Gate::authorize('record-payments');
        $validated = $request->validate([
            'payment_type' => ['required', Rule::in(['down_payment', 'final_payment'])],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01'],
            'payment_method' => ['required', Rule::in(['cash', 'gcash'])],
            'reference_number' => ['required_if:payment_method,gcash', 'nullable', 'string', 'max:100'],
            'pickup_confirmed' => ['accepted_if:payment_type,final_payment'],
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
            $message = '50% down payment of ₱'.number_format($validated['amount'], 2).' recorded. Order is now Confirmed!';
        } else {
            $this->orderService->recordFinalPayment(
                $order,
                (float) $validated['amount'],
                $validated['payment_method'],
                $validated['reference_number'] ?? null,
                $user,
                null,
                $request->boolean('pickup_confirmed')
            );
            $message = 'Pickup completed. Final payment of ₱'.number_format($validated['amount'], 2).' verified and recorded.';
        }

        return back()->with('success', $message);
    }

    public function completePickup(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('record-payments');
        $validated = $request->validate([
            'payment_method' => ['required', Rule::in(['cash', 'gcash'])],
            'reference_number' => ['required_if:payment_method,gcash', 'nullable', 'string', 'max:100'],
            'pickup_confirmed' => ['accepted'],
        ]);
        $this->orderService->completePickup($order, $validated['payment_method'], $validated['reference_number'] ?? null,
            $request->user(), $request->boolean('pickup_confirmed'));

        return back()->with('success', 'Pickup completed. The verified remaining balance is recorded and the order is Completed.');
    }
}
