@extends('layouts.admin')

@section('title', 'Edit User')

@section('content')
<div class="max-w-xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-stone-900">Edit User Details</h1>
        <a href="{{ route('users.index') }}" class="text-xs text-rose-900 font-bold hover:underline">
            ← Back to Users
        </a>
    </div>

    <div class="bg-white border border-stone-200 rounded-2xl p-6 shadow-sm">
        <form action="{{ route('users.update', $user) }}" method="POST" class="space-y-4">
            @csrf
            @method('PATCH')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold uppercase text-stone-700 mb-1">First Name <span class="text-red-500">*</span></label>
                    <input required name="first_name" value="{{ old('first_name', $user->first_name) }}" class="w-full text-xs rounded-xl border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Middle Name <span class="text-stone-400 font-normal">(optional)</span></label>
                    <input name="middle_name" value="{{ old('middle_name', $user->middle_name) }}" class="w-full text-xs rounded-xl border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                <input required name="last_name" value="{{ old('last_name', $user->last_name) }}" class="w-full text-xs rounded-xl border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Email Address <span class="text-red-500">*</span></label>
                <input required type="email" name="email" value="{{ old('email', $user->email) }}" class="w-full text-xs rounded-xl border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Role <span class="text-red-500">*</span></label>
                <select name="role" class="w-full text-xs rounded-xl border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
                    <option value="assistant" @selected(old('role', $user->role) === 'assistant')>Assistant</option>
                    <option value="owner" @selected(old('role', $user->role) === 'owner')>Owner</option>
                </select>
            </div>

            <div class="border-t pt-3">
                <span class="block text-xs font-bold uppercase text-stone-700 mb-2">Change Password</span>
                <p class="text-[11px] text-stone-400 mb-3">Leave blank to keep the current password.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-stone-600 mb-1">New Password</label>
                        <input type="password" name="password" class="w-full text-xs rounded-xl border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-stone-600 mb-1">Confirm Password</label>
                        <input type="password" name="password_confirmation" class="w-full text-xs rounded-xl border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
                    </div>
                </div>
            </div>

            <input type="hidden" name="is_active" value="0">
            <div class="flex items-center gap-2 pt-2 border-t">
                <input type="checkbox" name="is_active" value="1" id="edit_is_active" @checked(old('is_active', $user->is_active)) @disabled(auth()->id() === $user->id) class="rounded text-rose-900 focus:ring-rose-800">
                <label for="edit_is_active" class="text-xs text-stone-700 font-semibold cursor-pointer">
                    Active Account
                    @if(auth()->id() === $user->id)
                        <span class="text-stone-400 font-normal">(Cannot deactivate own account)</span>
                    @endif
                </label>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t">
                <a href="{{ route('users.index') }}" class="px-4 py-2 text-xs text-stone-600">Cancel</a>
                <button type="submit" class="px-5 py-2 bg-rose-900 hover:bg-rose-800 text-white text-xs font-bold rounded-xl shadow">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
