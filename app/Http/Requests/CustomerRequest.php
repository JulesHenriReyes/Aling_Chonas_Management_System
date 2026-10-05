<?php

namespace App\Http\Requests;

use App\Support\PhilippineContact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-customers');
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone_number' => PhilippineContact::rules(),
        ];
    }
}
