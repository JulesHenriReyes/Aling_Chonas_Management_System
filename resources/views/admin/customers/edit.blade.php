@extends('layouts.admin')

@section('title', 'Edit Customer')

@section('content')
<div class="max-w-xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-stone-900">Edit Customer Details</h1>
        <a href="{{ route('customers.show', $customer) }}" class="text-xs text-rose-900 font-bold hover:underline">
            ← Back
        </a>
    </div>

    <div class="bg-white rounded-2xl border border-stone-200 p-6 shadow-sm">
        <form action="{{ route('customers.update', $customer) }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-xs font-bold uppercase text-stone-700 mb-1">First Name <span class="text-red-500">*</span></label>
                <input type="text" name="first_name" value="{{ old('first_name', $customer->first_name) }}" required 
                       class="w-full text-xs rounded-xl border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Middle Name <span class="text-stone-400 font-normal">(optional)</span></label>
                <input type="text" name="middle_name" value="{{ old('middle_name', $customer->middle_name) }}" 
                       class="w-full text-xs rounded-xl border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                <input type="text" name="last_name" value="{{ old('last_name', $customer->last_name) }}" required 
                       class="w-full text-xs rounded-xl border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Phone Number <span class="text-red-500">*</span></label>
                <input type="tel" name="phone_number" value="{{ old('phone_number', $customer->phone_number) }}" required 
                       class="w-full text-xs rounded-xl border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
            </div>

            <div class="pt-4 flex items-center justify-end gap-3">
                <a href="{{ route('customers.show', $customer) }}" class="px-4 py-2 text-xs text-stone-600">Cancel</a>
                <button type="submit" class="px-5 py-2 bg-rose-900 hover:bg-rose-800 text-white font-bold text-xs rounded-xl shadow">
                    Update Details
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
