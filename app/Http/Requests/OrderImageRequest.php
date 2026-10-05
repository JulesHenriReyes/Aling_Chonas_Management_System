<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class OrderImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-orders');
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'exists:orders,id'],
            'image' => ['required', 'file', 'image', 'mimes:jpeg,png,jpg', 'max:5120'],
        ];
    }
}
