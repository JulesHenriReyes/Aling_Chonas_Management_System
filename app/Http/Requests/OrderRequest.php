<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'pickup_date' => ['required', 'date'],
            'pickup_time' => ['required'],
            'notes_text' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.layers' => ['nullable', 'integer', 'min:1'],
            'items.*.themes' => ['nullable', 'string', 'max:255'],
            'items.*.special_request' => ['nullable', 'string'],
        ];
    }
}
