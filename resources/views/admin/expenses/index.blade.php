@extends('layouts.admin')

@section('title', 'Expenses')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-cocoa-600">Expense Management</h1>
            <p class="text-sm text-cocoa-400 mt-1">Record operating costs used in financial reporting.</p>
        </div>
        <div class="text-right">
            <div class="text-xs font-semibold text-cocoa-400">Filtered total</div>
            <div class="font-bold text-cocoa-600 text-lg">₱{{ number_format($totalExpenses, 2) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <form action="{{ route('expenses.store') }}" method="POST" class="bg-white border border-cocoa-100 rounded-xl p-5 space-y-4">
            @csrf
            <h2 class="text-sm font-semibold text-cocoa-600">Record Expense</h2>
            <div>
                <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-expenses-index-blade-php-1">Description</label>
                <input id="field-admin-expenses-index-blade-php-1" name="description" value="{{ old('description') }}" required class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50" placeholder="e.g. Flour purchase">
            </div>
            <div>
                <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-expenses-index-blade-php-2">category</label>
                <select id="field-admin-expenses-index-blade-php-2" name="category" required class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300">
                    @foreach (['ingredients', 'packaging', 'equipment', 'miscellaneous'] as $category)
                        <option value="{{ $category }}" @selected(old('category') === $category)>{{ ucfirst($category) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-expenses-index-blade-php-3">amount</label>
                    <input id="field-admin-expenses-index-blade-php-3" type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-expenses-index-blade-php-4">date</label>
                    <input id="field-admin-expenses-index-blade-php-4" type="date" name="expense_date" value="{{ old('expense_date', today()->toDateString()) }}" required class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300">
                </div>
            </div>
            <button class="w-full bg-cocoa-600 hover:bg-cocoa-700 text-white font-semibold text-sm px-4 py-2.5 rounded-lg transition">Save Expense</button>
        </form>

        <div class="lg:col-span-2 space-y-4">
            <form method="GET" class="filter-bar">
                <div>
                    <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-expenses-index-blade-php-5">category</label>
                    <select id="field-admin-expenses-index-blade-php-5" name="category" class="text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300">
                        <option value="">All categories</option>
                        @foreach (['ingredients', 'packaging', 'equipment', 'miscellaneous'] as $category)
                            <option value="{{ $category }}" @selected(request('category') === $category)>{{ ucfirst($category) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-expenses-index-blade-php-6">From</label>
                    <input id="field-admin-expenses-index-blade-php-6" type="date" name="start_date" value="{{ request('start_date') }}" class="text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-expenses-index-blade-php-7">To</label>
                    <input id="field-admin-expenses-index-blade-php-7" type="date" name="end_date" value="{{ request('end_date') }}" class="text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300">
                </div>
                <button class="bg-cocoa-600 hover:bg-cocoa-700 text-white font-semibold text-sm px-4 py-2 rounded-lg transition min-h-[44px]">Filter</button>
            </form>

            <div class="bg-white border border-cocoa-100 rounded-xl overflow-hidden">
                <div class="table-scroll" role="region" aria-label="Scrollable data table" tabindex="0"><table class="w-full text-left">
                    <thead class="bg-cream-100 text-cocoa-400 text-xs  font-semibold">
                        <tr>
                            <th class="p-3">Date</th>
                            <th class="p-3">Description</th>
                            <th class="p-3">Category</th>
                            <th class="p-3">Recorded by</th>
                            <th class="p-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-cocoa-100/60">
                        @forelse ($expenses as $expense)
                            <tr class="text-sm text-cocoa-500 hover:bg-cream-50 transition">
                                <td class="p-3">{{ $expense->expense_date->format('M d, Y') }}</td>
                                <td class="p-3 font-semibold text-cocoa-600">{{ $expense->description }}</td>
                                <td class="p-3 capitalize">{{ $expense->category }}</td>
                                <td class="p-3">{{ $expense->user->full_name }}</td>
                                <td class="p-3 text-right font-semibold">₱{{ number_format($expense->amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-12 text-center text-sm text-cocoa-400">No expenses found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table></div>
            </div>
            {{ $expenses->links() }}
        </div>
    </div>
</div>
@endsection
