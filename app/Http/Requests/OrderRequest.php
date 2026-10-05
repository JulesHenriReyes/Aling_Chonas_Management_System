<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-orders');
    }

    public function rules(): array
    {
        return CatalogOrderRules::order() + [
            'customer_id' => ['required', 'exists:customers,id'],
        ];
    }
}
