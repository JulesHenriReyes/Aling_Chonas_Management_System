@extends('layouts.admin')

@section('title', 'Expenses')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-stone-900">Expense Management</h1>
            <p class="text-xs text-stone-500 mt-1">Record operating costs used in financial reporting.</p>
        </div>
        <div class="text-right">
            <div class="text-[10px] uppercase tracking-wide text-stone-400">Filtered total</div>
            <div class="font-bold text-rose-900">₱{{ number_format($totalExpenses, 2) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <form action="{{ route('expenses.store') }}" method="POST" class="bg-white border border-stone-200 rounded-2xl p-5 shadow-sm space-y-4">
            @csrf
            <h2 class="font-bold text-sm text-stone-900">Record Expense</h2>
            <div>
                <label class="block text-xs font-semibold text-stone-700 mb-1">Description</label>
                <input name="description" value="{{ old('description') }}" required class="w-full text-sm rounded-lg border-stone-300" placeholder="e.g. Flour purchase">
            </div>
            <div>
                <label class="block text-xs font-semibold text-stone-700 mb-1">Category</label>
                <select name="category" required class="w-full text-sm rounded-lg border-stone-300">
                    @foreach (['ingredients', 'packaging', 'equipment', 'miscellaneous'] as $category)
                        <option value="{{ $category }}" @selected(old('category') === $category)>{{ ucfirst($category) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-stone-700 mb-1">Amount</label>
                    <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required class="w-full text-sm rounded-lg border-stone-300">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-stone-700 mb-1">Expense date</label>
                    <input type="date" name="expense_date" value="{{ old('expense_date', today()->toDateString()) }}" required class="w-full text-sm rounded-lg border-stone-300">
                </div>
            </div>
            <button class="w-full py-2 bg-rose-900 hover:bg-rose-800 text-white text-xs font-bold rounded-lg">Save Expense</button>
        </form>

        <div class="lg:col-span-2 space-y-4">
            <form method="GET" class="bg-white border border-stone-200 rounded-2xl p-4 shadow-sm flex flex-wrap gap-3 items-end">
                <div>
                    <label class="block text-[10px] uppercase font-bold text-stone-500 mb-1">Category</label>
                    <select name="category" class="text-sm rounded-lg border-stone-300">
                        <option value="">All categories</option>
                        @foreach (['ingredients', 'packaging', 'equipment', 'miscellaneous'] as $category)
                            <option value="{{ $category }}" @selected(request('category') === $category)>{{ ucfirst($category) }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="block text-[10px] uppercase font-bold text-stone-500 mb-1">From</label><input type="date" name="start_date" value="{{ request('start_date') }}" class="text-sm rounded-lg border-stone-300"></div>
                <div><label class="block text-[10px] uppercase font-bold text-stone-500 mb-1">To</label><input type="date" name="end_date" value="{{ request('end_date') }}" class="text-sm rounded-lg border-stone-300"></div>
                <button class="px-4 py-2 bg-stone-800 text-white text-xs font-bold rounded-lg">Filter</button>
            </form>

            <div class="bg-white border border-stone-200 rounded-2xl shadow-sm overflow-hidden">
                <table class="w-full text-left text-xs">
                    <thead class="bg-stone-50 text-stone-500 uppercase text-[10px]"><tr><th class="p-3">Date</th><th class="p-3">Description</th><th class="p-3">Category</th><th class="p-3">Recorded by</th><th class="p-3 text-right">Amount</th></tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($expenses as $expense)
                            <tr><td class="p-3">{{ $expense->expense_date->format('M d, Y') }}</td><td class="p-3 font-medium">{{ $expense->description }}</td><td class="p-3 capitalize">{{ $expense->category }}</td><td class="p-3">{{ $expense->user->full_name }}</td><td class="p-3 text-right font-bold">₱{{ number_format($expense->amount, 2) }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="p-8 text-center text-stone-400">No expenses found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $expenses->links() }}
        </div>
    </div>
</div>
@endsection
