<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'supply_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:ingredients,packaging'],
            'unit' => ['required', 'string', 'max:50'],
            'current_quantity' => ['sometimes', 'numeric', 'min:0'],
            'reorder_level' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
