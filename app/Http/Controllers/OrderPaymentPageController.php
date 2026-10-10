<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentSetting;
use App\Services\PaymentReviewService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class OrderPaymentPageController extends Controller
{
    private function order(string $token): Order
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $token), 404);

        return Order::whereNull('user_id')->where('private_token', $token)->firstOrFail();
    }

    public function show(string $token)
    {
        $order = $this->order($token)->load(['orderDetails.addOns', 'orderDetails.product', 'payments', 'paymentProofs']);
        $settings = PaymentSetting::first();

        return view('public.payment', compact('order', 'settings'));
    }

    public function submit(Request $request, string $token, PaymentReviewService $reviews)
    {
        $order = $this->order($token);
        try {
            $data = $request->validate([
                'receipt' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                'reference_number' => ['required', 'string', 'max:100'],
            ]);
            $reviews->submit($order, $data['receipt'], $data['reference_number']);
        } catch (ValidationException $exception) {
            // The browser's previous page may belong to staff or an older session.
            // Keep Laravel's input/error handling and JSON 422 responses intact.
            throw $exception->redirectTo(route('public.order.payment', $token));
        }

        return redirect()->route('public.order.payment', $token)->with('success', 'Receipt submitted. Staff will verify it against the business GCash account.');
    }

    public function cancel(Request $request, string $token, OrderService $orders)
    {
        $this->order($token);
        try {
            $request->validate(['confirm_cancellation' => ['required', 'accepted']]);
            $order = $orders->cancelPublicOrder($token);
        } catch (ValidationException $exception) {
            throw $exception->redirectTo(route('public.order.payment', $token));
        }

        $message = $order->hasVerifiedPayment()
            ? 'Your order is cancelled. Your verified 50% deposit has been retained and will not be refunded.'
            : 'Your order is cancelled. No verified payment is recorded.';

        return redirect()->route('public.order.payment', $token)->with('success', $message);
    }

    public function qr(Request $request, string $token)
    {
        $order = $this->order($token);
        abort_unless($order->canSubmitReceipt(), 403, 'Payment is available only after staff confirmation, with no receipt awaiting review.');
        $settings = PaymentSetting::first();
        abort_unless($settings?->isConfigured(), 404);

        $filename = 'Aling-Chona-GCash.'.pathinfo($settings->qr_path, PATHINFO_EXTENSION);

        return $request->boolean('inline') ? Storage::disk('public')->response($settings->qr_path, $filename)
            : Storage::disk('public')->download($settings->qr_path, $filename);
    }

    public function saveLink(string $token)
    {
        $order = $this->order($token);
        $content = "Aling Chona Cakes & Cupcakes\nOrder {$order->order_number}\nKeep this private link safe. Anyone with it can view this order, submit a receipt after staff confirmation, and cancel an eligible order.\n".route('public.order.payment', $token)."\n";

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$order->order_number.'-private-link.txt"',
        ]);
    }
}
