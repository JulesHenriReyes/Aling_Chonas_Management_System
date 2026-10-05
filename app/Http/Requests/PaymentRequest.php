<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('record-payments');
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'exists:orders,id'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01'],
            'payment_type' => ['required', 'in:down_payment,final_payment'],
            'payment_method' => ['required', 'in:cash,gcash'],
            'reference_number' => ['required_if:payment_method,gcash', 'nullable', 'string', 'max:100'],
            'pickup_confirmed' => ['accepted_if:payment_type,final_payment'],
        ];
    }
}
