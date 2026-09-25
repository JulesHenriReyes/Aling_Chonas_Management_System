@extends('layouts.admin')

@section('title', 'Manage Customers')

@section('content')
<div class="space-y-6">
    <div class="page-heading">
        <div>
            <h1 class="text-xl font-bold text-cocoa-600">Customer Management</h1>
            <p class="text-sm text-cocoa-400 mt-1">Directory of customers, order history, and contact details.</p>
        </div>
        <a href="{{ route('customers.create') }}" class="bg-cocoa-600 hover:bg-cocoa-700 text-white font-semibold text-sm px-4 py-2 rounded-lg transition"><x-icon name="plus" class="mr-1" /> Add Customer
        </a>
    </div>

    <!-- Search Form -->
    <div class="">
        <form action="{{ route('customers.index') }}" method="GET" class="flex flex-wrap gap-3">
            <input aria-label="Search" type="text" name="search" value="{{ request('search') }}" 
                   placeholder="Search by First Name, Last Name, or Phone Number..." 
                   class="flex-grow min-w-0 text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
            <button type="submit" class="bg-white border border-cocoa-100 text-cocoa-500 hover:bg-cream-100 font-medium text-sm px-4 py-2 rounded-lg transition">
                Search
            </button>
            @if (request('search'))
                <a href="{{ route('customers.index') }}" class="text-cocoa-500 hover:text-cocoa-600 font-medium text-sm self-center px-3 py-2">Reset</a>
            @endif
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-cocoa-100 overflow-hidden">
        @if ($customers->isEmpty())
            <div class="py-12 text-center text-sm text-cocoa-400">No customer records found.</div>
        @else
            <div class="table-scroll" role="region" aria-label="Scrollable data table" tabindex="0">
                <table class="w-full text-left">
                    <thead class="bg-cream-100 text-cocoa-400 text-xs  font-semibold">
                        <tr>
                            <th class="p-4">Customer Name</th>
                            <th class="p-4">Phone Number</th>
                            <th class="p-4">Total Orders</th>
                            <th class="p-4">Created Date</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-cocoa-100/60">
                        @foreach ($customers as $c)
                            <tr class="text-sm text-cocoa-500 hover:bg-cream-50 transition">
                                <td class="p-4 font-semibold text-cocoa-600">
                                    <a href="{{ route('customers.show', $c) }}" class="hover:text-cocoa-700 hover:underline">
                                        {{ $c->full_name }}
                                    </a>
                                </td>
                                <td class="p-4 font-mono">{{ $c->phone_number }}</td>
                                <td class="p-4">
                                    <span class="text-sm text-cocoa-500">
                                        {{ $c->orders_count }} orders
                                    </span>
                                </td>
                                <td class="p-4 text-cocoa-400">{{ $c->created_at->format('M d, Y') }}</td>
                                <td class="p-4 text-right space-x-2">
                                    <a href="{{ route('customers.show', $c) }}" class="text-cocoa-600 font-medium hover:underline">View</a>
                                    <a href="{{ route('customers.edit', $c) }}" class="text-cocoa-400 hover:text-cocoa-600 transition hover:underline">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-cocoa-100/60">
                {{ $customers->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
