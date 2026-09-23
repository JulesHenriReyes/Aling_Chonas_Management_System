@extends('layouts.admin')

@section('title', 'User Management')

@section('content')
<div class="space-y-6"><div class="flex items-center justify-between"><div><h1 class="text-2xl font-bold text-stone-900">User Management</h1><p class="text-xs text-stone-500 mt-1">Owner-only management of internal Owner and Assistant accounts.</p></div><a href="{{ route('users.create') }}" class="px-4 py-2 bg-rose-900 text-white text-xs font-bold rounded-lg">+ Add User</a></div><div class="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden"><table class="w-full text-left text-xs"><thead class="bg-stone-50 text-stone-500 uppercase text-[10px]"><tr><th class="p-3">Name</th><th class="p-3">Email</th><th class="p-3">Role</th><th class="p-3">Status</th><th class="p-3"></th></tr></thead><tbody class="divide-y divide-stone-100">@foreach ($users as $user)<tr><td class="p-3 font-medium">{{ $user->full_name }}</td><td class="p-3">{{ $user->email }}</td><td class="p-3 capitalize">{{ $user->role }}</td><td class="p-3">{{ $user->is_active ? 'Active' : 'Inactive' }}</td><td class="p-3 text-right"><a class="text-rose-900 font-bold hover:underline" href="{{ route('users.edit', $user) }}">Edit</a></td></tr>@endforeach</tbody></table></div></div>
@endsection
