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
                    <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-users-create-blade-php-1">first name <span class="text-red-500">*</span></label>
                    <input autocomplete="given-name" id="field-admin-users-create-blade-php-1" required name="first_name" value="{{ old('first_name') }}" class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-users-create-blade-php-2">middle name <span class="text-cocoa-400 font-normal">(optional)</span></label>
                    <input autocomplete="additional-name" id="field-admin-users-create-blade-php-2" name="middle_name" value="{{ old('middle_name') }}" class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-users-create-blade-php-3">last name <span class="text-red-500">*</span></label>
                <input autocomplete="family-name" id="field-admin-users-create-blade-php-3" required name="last_name" value="{{ old('last_name') }}" class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
            </div>

            <div>
                <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-users-create-blade-php-4">Email address <span class="text-red-500">*</span></label>
                <input autocomplete="email" id="field-admin-users-create-blade-php-4" required type="email" name="email" value="{{ old('email') }}" class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
            </div>

            <div>
                <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-users-create-blade-php-5">role <span class="text-red-500">*</span></label>
                <select id="field-admin-users-create-blade-php-5" name="role" class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300">
                    <option value="assistant" @selected(old('role') === 'assistant')>Assistant</option>
                    <option value="owner" @selected(old('role') === 'owner')>Owner</option>
                </select>
                <p class="text-xs text-cocoa-400 mt-1.5">Owners can manage users; Assistants have access to all business operations.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <div>
                    <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-users-create-blade-php-6">password <span class="text-red-500">*</span></label>
                    <input id="field-admin-users-create-blade-php-6" required type="password" name="password" class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-users-create-blade-php-7">confirm password <span class="text-red-500">*</span></label>
                    <input id="field-admin-users-create-blade-php-7" required type="password" name="password_confirmation" class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300">
                </div>
            </div>

            <input type="hidden" name="is_active" value="0">
            <div class="flex items-center gap-2 pt-2">
                <input type="checkbox" name="is_active" value="1" id="is_active" checked class="rounded border-cocoa-100 text-cocoa-600 focus:ring-cocoa-300">
                <label for="is_active" class="text-sm font-medium text-cocoa-500 cursor-pointer">active account</label>
            </div>

            <div class="pt-6 flex items-center justify-end gap-3 border-t border-cocoa-100/60 mt-4">
                <a href="{{ route('users.index') }}" class="bg-white border border-cocoa-100 text-cocoa-500 hover:bg-cream-100 font-medium text-sm px-4 py-2 rounded-lg transition">Cancel</a>
                <button type="submit" class="bg-cocoa-600 hover:bg-cocoa-700 text-white font-semibold text-sm px-4 py-2 rounded-lg transition">
                    Create User
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
