@extends('layouts.admin')

@section('title', 'Add New Customer')

@section('content')
<div class="max-w-xl mx-auto space-y-6">
    <div class="page-heading">
        <h1 class="text-xl font-bold text-cocoa-600">Add New Customer</h1>
        <a href="{{ route('customers.index') }}" class="text-cocoa-500 hover:text-cocoa-600 font-medium text-sm transition">
            <x-icon name="arrow-left" class="mr-1" /> Back
        </a>
    </div>

    <div class="bg-white rounded-xl border border-cocoa-100 p-6">
        <form action="{{ route('customers.store') }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="customer-first-name">First Name <span class="text-red-500">*</span></label>
                <input autocomplete="given-name" id="customer-first-name" type="text" name="first_name" value="{{ old('first_name') }}" required 
                       class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
            </div>

            <div>
                <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="customer-middle-name">Middle Name <span class="text-cocoa-400 font-normal normal-case">(Optional)</span></label>
                <input autocomplete="additional-name" id="customer-middle-name" type="text" name="middle_name" value="{{ old('middle_name') }}" 
                       class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
            </div>

            <div>
                <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="customer-last-name">Last Name <span class="text-red-500">*</span></label>
                <input autocomplete="family-name" id="customer-last-name" type="text" name="last_name" value="{{ old('last_name') }}" required 
                       class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
            </div>

            <div>
                <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="customer-phone-number">Phone Number <span class="text-red-500">*</span></label>
                <input autocomplete="tel" id="customer-phone-number" type="tel" name="phone_number" maxlength="40" aria-describedby="customer-phone-help customer-phone-error" value="{{ old('phone_number') }}" required placeholder="e.g. 0917-123-4567"
                       class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
                <p id="customer-phone-help" class="text-xs text-cocoa-500 mt-1">For landlines, include the area code, e.g. 02 8123 4567 or 032 234 5678. +63 numbers are accepted.</p>
                @error('phone_number')<p id="customer-phone-error" role="alert" class="text-sm text-red-700">{{ $message }}</p>@enderror
            </div>

            <div class="pt-4 flex items-center justify-end gap-3">
                <a href="{{ route('customers.index') }}" class="px-4 py-2 text-sm font-medium text-cocoa-400 hover:text-cocoa-600 transition">Cancel</a>
                <button type="submit" class="bg-cocoa-600 hover:bg-cocoa-700 text-white font-semibold text-sm px-4 py-2 rounded-lg transition">
                    Save Customer
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
