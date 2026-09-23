@extends('layouts.admin')

@section('title', 'Financial Reports')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-stone-900">Financial Reports</h1>
            <p class="text-xs text-stone-500 mt-1">Operational financial reporting based on completed orders, retained deposits, and expenses.</p>
        </div>
        <div class="text-xs text-stone-500">
            Period: <span class="font-bold text-stone-800">{{ \Carbon\Carbon::parse($summary['period_start'])->format('M d, Y') }} — {{ \Carbon\Carbon::parse($summary['period_end'])->format('M d, Y') }}</span>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="bg-white rounded-2xl border border-stone-200 p-5 shadow-sm">
        <form method="GET" action="{{ route('reports.index') }}" class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-[10px] uppercase font-bold text-stone-600 mb-1">Start Date</label>
                <input type="date" name="start_date" value="{{ $summary['period_start'] }}" class="text-xs rounded-xl border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
            </div>
            <div>
                <label class="block text-[10px] uppercase font-bold text-stone-600 mb-1">End Date</label>
                <input type="date" name="end_date" value="{{ $summary['period_end'] }}" class="text-xs rounded-xl border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
            </div>
            <button type="submit" class="px-5 py-2.5 bg-rose-900 hover:bg-rose-800 text-white text-xs font-bold rounded-xl shadow transition">
                Filter Report
            </button>
            <a href="{{ route('reports.index') }}" class="px-3 py-2 text-stone-500 hover:text-stone-800 text-xs self-center">
                Current Month
            </a>
        </form>
    </div>

    <!-- Key Metrics Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Sales -->
        <div class="bg-white rounded-2xl border border-stone-200 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold uppercase tracking-wide text-stone-500">Completed Sales</span>
                <span class="text-sm">🎂</span>
            </div>
            <div class="text-2xl font-black text-stone-900 mt-2">₱{{ number_format($summary['sales'], 2) }}</div>
            <p class="text-[11px] text-stone-400 mt-1">Total value of completed orders</p>
        </div>

        <!-- Payment Collections -->
        <div class="bg-white rounded-2xl border border-stone-200 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold uppercase tracking-wide text-stone-500">Payment Collections</span>
                <span class="text-sm">💳</span>
            </div>
            <div class="text-2xl font-black text-emerald-700 mt-2">₱{{ number_format($summary['payment_collections'], 2) }}</div>
            <p class="text-[11px] text-stone-400 mt-1">Cash & GCash deposits received</p>
        </div>

        <!-- Cancellation Income -->
        <div class="bg-white rounded-2xl border border-stone-200 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold uppercase tracking-wide text-stone-500">Cancellation Income</span>
                <span class="text-sm">⚠️</span>
            </div>
            <div class="text-2xl font-black text-amber-700 mt-2">₱{{ number_format($summary['cancellation_income'], 2) }}</div>
            <p class="text-[11px] text-stone-400 mt-1">Retained non-refundable down payments</p>
        </div>

        <!-- Expenses -->
        <div class="bg-white rounded-2xl border border-stone-200 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold uppercase tracking-wide text-stone-500">Total Expenses</span>
                <span class="text-sm">🧾</span>
            </div>
            <div class="text-2xl font-black text-rose-800 mt-2">₱{{ number_format($summary['expenses'], 2) }}</div>
            <p class="text-[11px] text-stone-400 mt-1">Operational costs & supply purchases</p>
        </div>
    </div>

    <!-- Operational Net Income Card -->
    <div class="bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-200 rounded-2xl p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="text-xs font-bold uppercase tracking-wide text-emerald-800">Operational Net Income</div>
                <div class="text-3xl font-black text-emerald-950 mt-1">₱{{ number_format($summary['operational_net_income'], 2) }}</div>
                <p class="text-xs text-emerald-700 mt-1">Formula: Sales + Cancellation Income − Total Expenses</p>
            </div>
            <div class="text-xs text-emerald-800 bg-white/70 backdrop-blur-sm px-4 py-3 rounded-xl border border-emerald-200/60 font-mono">
                <div>(₱{{ number_format($summary['sales'], 2) }} + ₱{{ number_format($summary['cancellation_income'], 2) }}) − ₱{{ number_format($summary['expenses'], 2) }}</div>
            </div>
        </div>
    </div>

    <!-- Expense Breakdown Table -->
    <div class="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b bg-stone-50/50 flex items-center justify-between">
            <h2 class="font-bold text-sm text-stone-900">Expense Breakdown by Category</h2>
            <a href="{{ route('expenses.index') }}" class="text-xs text-rose-900 font-bold hover:underline">Manage Expenses →</a>
        </div>
        <table class="w-full text-left text-xs">
            <thead class="bg-stone-50 text-stone-500 uppercase text-[10px]">
                <tr>
                    <th class="p-4">Category</th>
                    <th class="p-4 text-right">Total Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($expensesByCategory as $expense)
                    <tr class="hover:bg-stone-50/50 transition">
                        <td class="p-4 font-semibold text-stone-800 capitalize">{{ $expense->category }}</td>
                        <td class="p-4 text-right font-bold text-stone-900 font-mono">₱{{ number_format($expense->total_amount, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="p-8 text-center text-stone-400">No recorded expenses found in this date range.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
