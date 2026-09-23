@extends('layouts.admin')

@section('title', 'Add User')

@section('content')
<div class="max-w-xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-stone-900">Create Internal User</h1>
        <a href="{{ route('users.index') }}" class="text-xs text-rose-900 font-bold hover:underline">
            ← Back to Users
        </a>
    </div>

    <div class="bg-white border border-stone-200 rounded-2xl p-6 shadow-sm">
        <form action="{{ route('users.store') }}" method="POST" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold uppercase text-stone-700 mb-1">First Name <span class="text-red-500">*</span></label>
                    <input required name="first_name" value="{{ old('first_name') }}" class="w-full text-xs rounded-xl border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Middle Name <span class="text-stone-400 font-normal">(optional)</span></label>
                    <input name="middle_name" value="{{ old('middle_name') }}" class="w-full text-xs rounded-xl border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                <input required name="last_name" value="{{ old('last_name') }}" class="w-full text-xs rounded-xl border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Email Address <span class="text-red-500">*</span></label>
                <input required type="email" name="email" value="{{ old('email') }}" class="w-full text-xs rounded-xl border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Role <span class="text-red-500">*</span></label>
                <select name="role" class="w-full text-xs rounded-xl border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
                    <option value="assistant" @selected(old('role') === 'assistant')>Assistant</option>
                    <option value="owner" @selected(old('role') === 'owner')>Owner</option>
                </select>
                <p class="text-[11px] text-stone-400 mt-1">Owners can manage users; Assistants have access to all business operations.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Password <span class="text-red-500">*</span></label>
                    <input required type="password" name="password" class="w-full text-xs rounded-xl border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Confirm Password <span class="text-red-500">*</span></label>
                    <input required type="password" name="password_confirmation" class="w-full text-xs rounded-xl border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
                </div>
            </div>

            <input type="hidden" name="is_active" value="0">
            <div class="flex items-center gap-2 pt-1">
                <input type="checkbox" name="is_active" value="1" id="is_active" checked class="rounded text-rose-900 focus:ring-rose-800">
                <label for="is_active" class="text-xs text-stone-700 font-semibold cursor-pointer">Active Account</label>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t">
                <a href="{{ route('users.index') }}" class="px-4 py-2 text-xs text-stone-600">Cancel</a>
                <button type="submit" class="px-5 py-2 bg-rose-900 hover:bg-rose-800 text-white text-xs font-bold rounded-xl shadow">
                    Create User
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
