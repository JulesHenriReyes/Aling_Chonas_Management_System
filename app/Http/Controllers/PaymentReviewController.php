<?php

namespace App\Http\Controllers;

use App\Models\PaymentProof;
use App\Services\PaymentReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentReviewController extends Controller
{
    public function receipt(PaymentProof $proof)
    {
        abort_unless(Storage::disk('receipts')->exists($proof->file_path), 404);

        return Storage::disk('receipts')->response($proof->file_path, 'GCash-receipt.'.pathinfo($proof->file_path, PATHINFO_EXTENSION));
    }

    public function accept(Request $request, PaymentProof $proof, PaymentReviewService $reviews)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01'],
            'reference_number' => ['required', 'string', 'max:100'],
            'account_checked' => ['accepted'],
        ]);
        $reviews->accept($proof, (float) $data['amount'], $data['reference_number'], $request->user());

        return redirect()->route('orders.show', $proof->order_id)->with('success', 'GCash deposit verified. Order confirmed; start Preparing when baking begins.');
    }

    public function reject(Request $request, PaymentProof $proof, PaymentReviewService $reviews)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $reviews->reject($proof, $data['reason'], $request->user());

        return redirect()->route('orders.show', $proof->order_id)->with('success', 'Receipt rejected. The buyer can send a replacement through the same private link.');
    }
}
