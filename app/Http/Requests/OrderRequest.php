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
        return CatalogOrderRules::order() + [
            'customer_id' => ['required', 'exists:customers,id'],
        ];
    }
}
