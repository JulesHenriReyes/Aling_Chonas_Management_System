@extends('layouts.admin')

@section('title', 'User Management')

@section('content')
<div class="space-y-6">
    <div class="page-heading">
        <div>
            <h1 class="text-xl font-bold text-cocoa-600">User Management</h1>
            <p class="text-sm text-cocoa-400 mt-1">Owner-only management of internal Owner and Assistant accounts.</p>
        </div>
        <a href="{{ route('users.create') }}" class="ui-button primary">
            <x-icon name="plus" class="mr-1" /> Add User
        </a>
    </div>
    
    <div class="bg-white border border-cocoa-100 rounded-xl overflow-hidden">
        <div class="table-scroll" role="region" aria-label="Scrollable data table" tabindex="0">
            <table class="w-full text-left">
                <thead class="bg-cream-100 text-cocoa-400 text-xs font-semibold">
                    <tr>
                        <th class="p-4">Name</th>
                        <th class="p-4">Email</th>
                        <th class="p-4">Role</th>
                        <th class="p-4">Status</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-cocoa-100/60">
                    @foreach ($users as $user)
                        <tr class="text-sm text-cocoa-500 hover:bg-cream-50 transition">
                            <td class="p-4 font-semibold text-cocoa-600">{{ $user->full_name }}</td>
                            <td class="p-4">{{ $user->email }}</td>
                            <td class="p-4 capitalize">{{ $user->role }}</td>
                            <td class="p-4">
                                <x-status :value="$user->is_active ? 'active' : 'inactive'" :label="$user->is_active ? 'Active' : 'Inactive'" />
                            </td>
                            <td class="p-4 text-right">
                                <a class="ui-button" href="{{ route('users.edit', $user) }}">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
