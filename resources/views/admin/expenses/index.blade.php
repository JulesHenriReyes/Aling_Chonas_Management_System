@extends('layouts.admin')
@section('title', 'Expenses')
@section('content')
<div class="workspace" x-data="{ deleteModalOpen: false, deleteAction: '', expenseDesc: '', expenseVersion: 0 }">
    <header class="workspace-heading">
        <div>
            <h1>Expenses</h1>
            <p>Daily purchases and business costs.</p>
        </div>
        <div class="workspace-actions">
            <a class="ui-button" href="{{ route('expenses.history') }}">Audit history</a>
            <a class="ui-button primary" href="{{ route('expenses.create') }}">Add expense</a>
        </div>
    </header>

    <form method="GET" class="workspace-filters" aria-label="Filter expenses">
        <div class="filter-search">
            <label for="expense-q">Search</label>
            <input id="expense-q" name="q" value="{{ request('q') }}" placeholder="Description">
        </div>
        <div>
            <label for="expense-category">Category</label>
            <select id="expense-category" name="category">
                <option value="">All categories</option>
                @foreach(['ingredients', 'packaging', 'equipment', 'miscellaneous'] as $category)
                    <option @selected(request('category') === $category) value="{{ $category }}">{{ ucfirst($category) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="expense-from">From</label>
            <input id="expense-from" type="date" name="start_date" value="{{ request('start_date') }}">
        </div>
        <div>
            <label for="expense-to">To</label>
            <input id="expense-to" type="date" name="end_date" value="{{ request('end_date') }}">
        </div>
        <div>
            <label for="expense-status">Status</label>
            <select id="expense-status" name="status">
                @foreach(['active' => 'Active', 'voided' => 'Voided', 'all' => 'All records'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status', 'active') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <input type="hidden" name="sort" value="{{ request('sort', 'expense_date') }}">
        <input type="hidden" name="direction" value="{{ request('direction', 'desc') }}">
        <button class="ui-button" type="submit">Apply</button>
        <a class="ui-button quiet" href="{{ route('expenses.index') }}">Reset</a>
    </form>

    <div class="workspace-summary">
        <span>{{ number_format($expenses->total()) }} matching records</span>
        <p>Active total for these filters <strong>₱{{ number_format($totalExpenses, 2) }}</strong></p>
    </div>

    <div class="workspace-table table-scroll" role="region" aria-label="Expenses table" tabindex="0">
        <table>
            <thead>
                <tr>
                    <th><x-sort-heading column="expense_date" label="Expense date" /></th>
                    <th><x-sort-heading column="description" label="Description" /></th>
                    <th><x-sort-heading column="category" label="Category" /></th>
                    <th class="numeric"><x-sort-heading column="amount" label="Amount" /></th>
                    <th>Recorded by</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($expenses as $expense)
                    <tr>
                        <td class="nowrap">{{ $expense->expense_date->format('M d, Y') }}</td>
                        <td><a class="record-link" href="{{ route('expenses.show', $expense) }}">{{ $expense->description }}</a></td>
                        <td>{{ ucfirst($expense->category) }}</td>
                        <td class="numeric">₱{{ number_format($expense->amount, 2) }}</td>
                        <td>{{ $expense->user->full_name }}</td>
                        <td>
                            <x-status :value="$expense->trashed() ? 'voided' : 'active'" :label="$expense->trashed() ? 'Voided' : 'Active'" />
                        </td>
                        <td>
                            <details class="row-actions">
                                <summary>Actions</summary>
                                <div>
                                    <a href="{{ route('expenses.show', $expense) }}">View</a>
                                    @unless($expense->trashed())
                                        <a href="{{ route('expenses.edit', $expense) }}">Edit</a>
                                        <button type="button"
                                                class="text-left w-full text-red-600 hover:text-red-700 text-xs px-3 py-1.5 transition"
                                                data-action="{{ route('expenses.destroy', $expense) }}"
                                                data-desc="{{ $expense->description }} (₱{{ number_format($expense->amount, 2) }})"
                                                data-version="{{ $expense->version }}"
                                                @click="deleteModalOpen = true; deleteAction = $el.dataset.action; expenseDesc = $el.dataset.desc; expenseVersion = $el.dataset.version">
                                            Delete
                                        </button>
                                    @endunless
                                    <a href="{{ route('expenses.history', ['expense_id' => $expense->id]) }}">History</a>
                                </div>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty-state">No expenses match these filters. <a href="{{ route('expenses.index') }}">Clear filters</a> or add an expense.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <x-workspace-pagination :records="$expenses" />

    {{-- In-page Delete Confirmation Dialog --}}
    <div x-show="deleteModalOpen"
         x-cloak
         data-dialog
         role="dialog"
         aria-modal="true"
         aria-labelledby="delete-expense-dialog-title"
         @keydown.escape.window="deleteModalOpen = false"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="fixed inset-0 bg-black/40 transition-opacity" @click="deleteModalOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-md bg-white rounded-xl shadow-xl p-6 border border-cocoa-100 space-y-4">
                <div>
                    <h3 id="delete-expense-dialog-title" class="text-lg font-bold text-cocoa-900">Delete Expense</h3>
                    <p class="text-xs text-cocoa-500 mt-1">This removes the expense from active totals. Its original record and history remain available.</p>
                </div>
                <div class="p-3 bg-cream-50 rounded-lg border border-cocoa-100">
                    <p class="text-xs text-cocoa-400">Expense item</p>
                    <p class="text-sm font-semibold text-cocoa-800" x-text="expenseDesc"></p>
                </div>
                <form data-safe-form :action="deleteAction" method="POST" class="space-y-4">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="version" :value="expenseVersion">
                    <div>
                        <label for="modal-delete-reason" class="block text-xs font-semibold text-cocoa-600 mb-1">Reason for deletion *</label>
                        <textarea id="modal-delete-reason" name="reason" required maxlength="1000" rows="3" class="w-full text-sm rounded-lg border border-cocoa-200 focus:border-cocoa-500 focus:ring-1 focus:ring-cocoa-500 p-2" placeholder="e.g. Duplicate invoice, incorrect entry"></textarea>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" class="ui-button quiet" @click="deleteModalOpen = false">Cancel</button>
                        <button type="submit" class="ui-button danger">Delete and retain history</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
