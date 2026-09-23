@extends('layouts.admin')

@section('title', 'Manage Customers')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-stone-900">Customer Management</h1>
            <p class="text-xs text-stone-500 mt-1">Directory of customers, order history, and contact details.</p>
        </div>
        <a href="{{ route('customers.create') }}" class="px-4 py-2 bg-rose-900 hover:bg-rose-800 text-white font-bold text-xs rounded-xl shadow">
            + Add Customer
        </a>
    </div>

    <!-- Search Form -->
    <div class="bg-white p-4 rounded-2xl border border-stone-200 shadow-sm">
        <form action="{{ route('customers.index') }}" method="GET" class="flex gap-3">
            <input type="text" name="search" value="{{ request('search') }}" 
                   placeholder="Search by First Name, Last Name, or Phone Number..." 
                   class="flex-grow text-xs rounded-xl border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
            <button type="submit" class="px-4 py-2 bg-stone-800 hover:bg-stone-700 text-white text-xs font-bold rounded-xl shadow">
                Search
            </button>
            @if (request('search'))
                <a href="{{ route('customers.index') }}" class="px-3 py-2 text-stone-500 hover:text-stone-800 text-xs self-center">Reset</a>
            @endif
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
        @if ($customers->isEmpty())
            <div class="p-8 text-center text-stone-400 text-xs">No customer records found.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-stone-50 border-b text-stone-500 uppercase text-[10px]">
                        <tr>
                            <th class="p-4">Customer Name</th>
                            <th class="p-4">Phone Number</th>
                            <th class="p-4">Total Orders</th>
                            <th class="p-4">Created Date</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($customers as $c)
                            <tr class="hover:bg-stone-50 transition">
                                <td class="p-4 font-bold text-stone-900">
                                    <a href="{{ route('customers.show', $c) }}" class="hover:text-rose-900 hover:underline">
                                        {{ $c->full_name }}
                                    </a>
                                </td>
                                <td class="p-4 font-mono text-stone-700">{{ $c->phone_number }}</td>
                                <td class="p-4">
                                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-stone-100 text-stone-800">
                                        {{ $c->orders_count }} orders
                                    </span>
                                </td>
                                <td class="p-4 text-stone-500">{{ $c->created_at->format('M d, Y') }}</td>
                                <td class="p-4 text-right space-x-2">
                                    <a href="{{ route('customers.show', $c) }}" class="text-rose-900 font-bold hover:underline">View</a>
                                    <a href="{{ route('customers.edit', $c) }}" class="text-stone-500 hover:underline">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t">
                {{ $customers->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
