<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'exists:orders,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_type' => ['required', 'in:down_payment,final_payment'],
            'payment_method' => ['required', 'in:cash,gcash'],
            'reference_number' => ['required_if:payment_method,gcash', 'nullable', 'string', 'max:100'],
        ];
    }
}
