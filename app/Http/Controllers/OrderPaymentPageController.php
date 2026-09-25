<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentSetting;
use App\Services\PaymentReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OrderPaymentPageController extends Controller
{
    private function order(string $token): Order
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $token), 404);

        return Order::whereNull('user_id')->where('private_token', $token)->firstOrFail();
    }

    public function show(string $token)
    {
        $order = $this->order($token)->load(['orderDetails.addOns', 'orderDetails.product', 'payments', 'paymentProofs', 'refund']);
        $settings = PaymentSetting::first();

        return view('public.payment', compact('order', 'settings'));
    }

    public function submit(Request $request, string $token, PaymentReviewService $reviews)
    {
        $order = $this->order($token);
        $data = $request->validate([
            'receipt' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'reference_number' => ['required', 'string', 'max:100'],
        ]);
        $reviews->submit($order, $data['receipt'], $data['reference_number']);

        return redirect()->route('public.order.payment', $token)->with('success', 'Receipt submitted. Staff will verify it against the business GCash account.');
    }

    public function qr(string $token)
    {
        $this->order($token);
        $settings = PaymentSetting::first();
        abort_unless($settings?->isConfigured(), 404);

        return Storage::disk('public')->download($settings->qr_path, 'Aling-Chona-GCash.'.pathinfo($settings->qr_path, PATHINFO_EXTENSION));
    }

    public function saveLink(string $token)
    {
        $order = $this->order($token);
        $content = "Aling Chona Cakes & Cupcakes\nOrder {$order->order_number}\nKeep this private link safe. Anyone with it can view this order and submit a receipt.\n".route('public.order.payment', $token)."\n";

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$order->order_number.'-private-link.txt"',
        ]);
    }
}
