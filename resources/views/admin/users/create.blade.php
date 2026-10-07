@extends('layouts.admin')

@section('title', 'Add User')

@section('content')
<div class="max-w-xl mx-auto space-y-6">
    <div class="page-heading">
        <h1 class="text-xl font-bold text-cocoa-600">Create Internal User</h1>
        <a href="{{ route('users.index') }}" class="text-cocoa-500 hover:text-cocoa-600 font-medium text-sm">
            <x-icon name="arrow-left" class="mr-1" /> Back to Users
        </a>
    </div>

    <div class="bg-white border border-cocoa-100 rounded-xl p-6">
        <form action="{{ route('users.store') }}" method="POST" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="user-first-name">First Name <span class="text-red-500">*</span></label>
                    <input autocomplete="given-name" id="user-first-name" required name="first_name" value="{{ old('first_name') }}" class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="user-middle-name">Middle Name <span class="text-cocoa-400 font-normal">(Optional)</span></label>
                    <input autocomplete="additional-name" id="user-middle-name" name="middle_name" value="{{ old('middle_name') }}" class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="user-last-name">Last Name <span class="text-red-500">*</span></label>
                <input autocomplete="family-name" id="user-last-name" required name="last_name" value="{{ old('last_name') }}" class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
            </div>

            <div>
                <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="user-email">Email Address <span class="text-red-500">*</span></label>
                <input autocomplete="email" id="user-email" required type="email" name="email" value="{{ old('email') }}" class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
            </div>

            <div>
                <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="user-role">Role <span class="text-red-500">*</span></label>
                <select id="user-role" name="role" class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300">
                    <option value="assistant" @selected(old('role') === 'assistant')>Assistant</option>
                    <option value="owner" @selected(old('role') === 'owner')>Owner</option>
                </select>
                <p class="text-xs text-cocoa-400 mt-1.5">Owners can manage users; Assistants have access to all business operations.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <div>
                    <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="user-password">Password <span class="text-red-500">*</span></label>
                    <input id="user-password" required type="password" name="password" class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="user-password-confirmation">Confirm Password <span class="text-red-500">*</span></label>
                    <input id="user-password-confirmation" required type="password" name="password_confirmation" class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300">
                </div>
            </div>

            <input type="hidden" name="is_active" value="0">
            <div class="flex items-center gap-2 pt-2">
                <input type="checkbox" name="is_active" value="1" id="is_active" checked class="rounded border-cocoa-100 text-cocoa-600 focus:ring-cocoa-300">
                <label for="is_active" class="text-sm font-medium text-cocoa-500 cursor-pointer">Active Account</label>
            </div>

            <div class="pt-6 flex items-center justify-end gap-3 border-t border-cocoa-100/60 mt-4">
                <a href="{{ route('users.index') }}" class="ui-button">Cancel</a>
                <button type="submit" class="ui-button primary">
                    Create User
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
