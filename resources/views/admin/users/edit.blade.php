@extends('layouts.admin')

@section('title', 'Edit User')

@section('content')
<div class="max-w-xl mx-auto space-y-6">
    <div class="page-heading">
        <h1 class="text-xl font-bold text-cocoa-600">Edit User Details</h1>
        <a href="{{ route('users.index') }}" class="text-cocoa-500 hover:text-cocoa-600 font-medium text-sm">
            <x-icon name="arrow-left" class="mr-1" /> Back to Users
        </a>
    </div>

    <div class="bg-white border border-cocoa-100 rounded-xl p-6">
        <form action="{{ route('users.update', $user) }}" method="POST" class="space-y-4">
            @csrf
            @method('PATCH')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="user-first-name">First Name <span class="text-red-500">*</span></label>
                    <input autocomplete="given-name" id="user-first-name" required name="first_name" value="{{ old('first_name', $user->first_name) }}" class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="user-middle-name">Middle Name <span class="text-cocoa-400 font-normal">(Optional)</span></label>
                    <input autocomplete="additional-name" id="user-middle-name" name="middle_name" value="{{ old('middle_name', $user->middle_name) }}" class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="user-last-name">Last Name <span class="text-red-500">*</span></label>
                <input autocomplete="family-name" id="user-last-name" required name="last_name" value="{{ old('last_name', $user->last_name) }}" class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
            </div>

            <div>
                <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="user-email">Email Address <span class="text-red-500">*</span></label>
                <input autocomplete="email" id="user-email" required type="email" name="email" value="{{ old('email', $user->email) }}" class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
            </div>

            <div>
                <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="user-role">Role <span class="text-red-500">*</span></label>
                <select id="user-role" name="role" class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300">
                    <option value="assistant" @selected(old('role', $user->role) === 'assistant')>Assistant</option>
                    <option value="owner" @selected(old('role', $user->role) === 'owner')>Owner</option>
                </select>
            </div>

            <div class="border-t border-cocoa-100/60 pt-4 mt-2">
                <span class="block text-xs font-semibold text-cocoa-600 mb-1.5">Change Password</span>
                <p class="text-xs text-cocoa-400 mb-3">Leave blank to keep the current password.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-cocoa-500 mb-1" for="user-new-password">New Password</label>
                        <input id="user-new-password" type="password" name="password" class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-cocoa-500 mb-1" for="user-password-confirmation">Confirm Password</label>
                        <input id="user-password-confirmation" type="password" name="password_confirmation" class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300">
                    </div>
                </div>
            </div>

            <input type="hidden" name="is_active" value="0">
            <div class="flex items-center gap-2 pt-4 border-t border-cocoa-100/60 mt-2">
                <input type="checkbox" name="is_active" value="1" id="edit_is_active" @checked(old('is_active', $user->is_active)) @disabled(auth()->id() === $user->id) class="rounded border-cocoa-100 text-cocoa-600 focus:ring-cocoa-300 disabled:opacity-50">
                <label for="edit_is_active" class="text-sm font-medium text-cocoa-500 cursor-pointer">
                    Active Account
                    @if(auth()->id() === $user->id)
                        <span class="text-cocoa-400 font-normal text-xs ml-1">(Cannot deactivate own account)</span>
                    @endif
                </label>
            </div>

            <div class="pt-6 flex items-center justify-end gap-3 border-t border-cocoa-100/60 mt-4">
                <a href="{{ route('users.index') }}" class="ui-button">Cancel</a>
                <button type="submit" class="ui-button primary">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
