@extends('layouts.admin')

@section('title', 'Financial Reports')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-cocoa-600">Financial Reports</h1>
            <p class="text-sm text-cocoa-400 mt-1">Operational financial reporting based on completed orders, retained deposits, and expenses.</p>
        </div>
        <div class="text-sm text-cocoa-500">
            Period: <span class="font-semibold text-cocoa-600">{{ \Carbon\Carbon::parse($summary['period_start'])->format('M d, Y') }} — {{ \Carbon\Carbon::parse($summary['period_end'])->format('M d, Y') }}</span>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="report-filter">
        <form method="GET" action="{{ route('reports.index') }}" class="filter-bar">
            <div>
                <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-reports-index-blade-php-1">Start date</label>
                <input id="field-admin-reports-index-blade-php-1" type="date" name="start_date" value="{{ $summary['period_start'] }}" class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300">
            </div>
            <div>
                <label class="block text-xs font-semibold text-cocoa-500 mb-1.5" for="field-admin-reports-index-blade-php-2">End date</label>
                <input id="field-admin-reports-index-blade-php-2" type="date" name="end_date" value="{{ $summary['period_end'] }}" class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300">
            </div>
            <button type="submit" class="bg-cocoa-600 hover:bg-cocoa-700 text-white font-semibold text-sm px-4 py-2 rounded-lg transition min-h-[44px]">
                Filter Report
            </button>
            <a href="{{ route('reports.index') }}" class="text-cocoa-500 hover:text-cocoa-600 font-medium text-sm self-center pb-2">
                Current Month
            </a>
        </form>
    </div>

    <!-- Key Metrics Grid -->
    <div class="metrics">
        <!-- Sales -->
        <div class="bg-white border border-cocoa-100 rounded-xl p-5">
            <div class="page-heading">
                <span class="text-xs font-semibold  text-cocoa-400 flex items-center gap-1.5">
                    Completed Sales
                </span>
            </div>
            <div class="text-2xl font-bold text-cocoa-600 mt-2">₱{{ number_format($summary['sales'], 2) }}</div>
            <p class="text-xs text-cocoa-400 mt-1">Total value of completed orders</p>
        </div>

        <!-- Payment Collections -->
        <div class="bg-white border border-cocoa-100 rounded-xl p-5">
            <div class="page-heading">
                <span class="text-xs font-semibold  text-cocoa-400">Payment Collections</span>
            </div>
            <div class="text-2xl font-bold text-cocoa-600 mt-2">₱{{ number_format($summary['payment_collections'], 2) }}</div>
            <p class="text-xs text-cocoa-400 mt-1">Verified payments less completed refunds</p>
        </div>

        <!-- Cancellation Income -->
        <div class="bg-white border border-cocoa-100 rounded-xl p-5">
            <div class="page-heading">
                <span class="text-xs font-semibold  text-cocoa-400">Cancellation Income</span>
            </div>
            <div class="text-2xl font-bold text-cocoa-600 mt-2">₱{{ number_format($summary['cancellation_income'], 2) }}</div>
            <p class="text-xs text-cocoa-400 mt-1">Retained non-refundable down payments</p>
        </div>

        <!-- Expenses -->
        <div class="bg-white border border-cocoa-100 rounded-xl p-5">
            <div class="page-heading">
                <span class="text-xs font-semibold  text-cocoa-400">Total Expenses</span>
            </div>
            <div class="text-2xl font-bold text-cocoa-600 mt-2">₱{{ number_format($summary['expenses'], 2) }}</div>
            <p class="text-xs text-cocoa-400 mt-1">Operational costs & supply purchases</p>
        </div>
    </div>

    <!-- Operational Net Income Card -->
    <div class="space-y-2 text-sm">
        <p>Refunds completed in this period: <strong>₱{{ number_format($summary['refunds_completed'], 2) }}</strong>. These are deducted from payment collections on their transfer date.</p>
        <p>Refunds still pending across all dates: <strong>₱{{ number_format($summary['refunds_pending'], 2) }}</strong>. This money is due back to buyers.</p>
        <p>Unverified receipts are excluded. Bakery-failure cancellations never count as retained cancellation income.</p>
    </div>
    <div class="bg-cream-50 border border-cocoa-100 rounded-xl p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="text-xs font-semibold text-cocoa-500">Operational Net Income</div>
                <div class="text-3xl font-bold text-cocoa-600 mt-1">₱{{ number_format($summary['operational_net_income'], 2) }}</div>
                <p class="text-sm text-cocoa-400 mt-1">Formula: Sales + Cancellation Income − Total Expenses</p>
            </div>
            <div class="text-sm text-cocoa-500">
                <div>(₱{{ number_format($summary['sales'], 2) }} + ₱{{ number_format($summary['cancellation_income'], 2) }}) − ₱{{ number_format($summary['expenses'], 2) }}</div>
            </div>
        </div>
    </div>

    <!-- Expense Breakdown Table -->
    <div class="bg-white border border-cocoa-100 rounded-xl overflow-hidden">
        <div class="p-4 border-b border-cocoa-100/60 bg-cream-50 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-cocoa-600">Expense Breakdown by Category</h2>
            <a href="{{ route('expenses.index') }}" class="text-cocoa-500 hover:text-cocoa-600 font-medium text-sm">Manage Expenses <x-icon name="arrow-right" class="ml-1" /></a>
        </div>
        <table class="w-full text-left">
            <thead class="bg-cream-100 text-cocoa-400 text-xs  font-semibold">
                <tr>
                    <th class="p-4">Category</th>
                    <th class="p-4 text-right">Total Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-cocoa-100/60">
                @forelse ($expensesByCategory as $expense)
                    <tr class="text-sm text-cocoa-500 hover:bg-cream-50 transition">
                        <td class="p-4 font-medium capitalize">{{ $expense->category }}</td>
                        <td class="p-4 text-right font-semibold text-cocoa-600">₱{{ number_format($expense->total_amount, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="p-12 text-center text-sm text-cocoa-400">No recorded expenses found in this date range.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
