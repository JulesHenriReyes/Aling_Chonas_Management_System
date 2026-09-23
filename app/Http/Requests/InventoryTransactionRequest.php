<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InventoryTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'supply_id' => ['required', 'exists:supplies,id'],
            'transaction_type' => ['required', 'in:stock_in,stock_out,adjustment'],
            'quantity' => ['required', 'numeric'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
