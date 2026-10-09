@extends('layouts.admin')
@section('title', 'Expenses')
@section('content')
@php
    $categoryLabels = [
        'ingredients' => 'Groceries',
        'packaging' => 'Packaging',
        'equipment' => 'Equipment',
        'miscellaneous' => 'Miscellaneous',
    ];
@endphp
<div class="workspace" x-data="{
    createModalOpen: {{ $errors->any() && old('_form') === 'create' ? 'true' : 'false' }},
    deleteModalOpen: false,
    deleteAction: '',
    expenseDesc: '',
    expenseVersion: 0
}">
    <header class="workspace-heading">
        <div>
            <h1>Expenses</h1>
            <p>Daily purchases and business costs.</p>
        </div>
        <div class="workspace-actions">
            <a class="ui-button" href="{{ route('expenses.history') }}">Audit history</a>
            <button type="button" class="ui-button primary" @click="createModalOpen = true">
                <x-icon name="plus" /> Add expense
            </button>
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
                    <option @selected(request('category') === $category) value="{{ $category }}">{{ $categoryLabels[$category] ?? ucfirst($category) }}</option>
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
        <table class="expenses-table">
            <thead>
                <tr>
                    <th><x-sort-heading column="expense_date" label="Expense date" /></th>
                    <th><x-sort-heading column="description" label="Description" /></th>
                    <th><x-sort-heading column="category" label="Category" /></th>
                    <th class="numeric"><x-sort-heading column="amount" label="Amount" /></th>
                    <th>Recorded by</th>
                    <th>Status</th>
                    <th class="text-right" style="width: 56px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($expenses as $expense)
                    <tr>
                        <td class="nowrap">{{ $expense->expense_date->format('M d, Y') }}</td>
                        <td><a class="record-link" href="{{ route('expenses.show', $expense) }}">{{ $expense->description }}</a></td>
                        <td>{{ $categoryLabels[$expense->category] ?? ucfirst($expense->category) }}</td>
                        <td class="numeric">₱{{ number_format($expense->amount, 2) }}</td>
                        <td>{{ $expense->user->full_name }}</td>
                        <td>
                            <x-status :value="$expense->trashed() ? 'voided' : 'active'" :label="$expense->trashed() ? 'Voided' : 'Active'" />
                        </td>
                        <td class="text-right" @click.stop>
                            <details class="row-actions supply-actions">
                                <summary aria-label="Actions for {{ $expense->description }}"><x-icon name="dots-vertical" /></summary>
                                <div>
                                    <a href="{{ route('expenses.show', $expense) }}">View</a>
                                    @unless($expense->trashed())
                                        <a href="{{ route('expenses.edit', $expense) }}">Edit</a>
                                        <button type="button"
                                                class="text-left w-full text-red-600 hover:text-red-700 text-xs px-3 py-1.5 transition"
                                                data-action="{{ route('expenses.destroy', $expense) }}"
                                                data-desc="{{ $expense->description }} (₱{{ number_format($expense->amount, 2) }})"
                                                data-version="{{ $expense->version }}"
                                                @click="deleteModalOpen = true; deleteAction = $el.dataset.action; expenseDesc = $el.dataset.desc; expenseVersion = $el.dataset.version; $el.closest('details')?.removeAttribute('open')">
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

    <!-- Add Expense Popup Modal -->
    <div x-show="createModalOpen"
         x-cloak
         data-dialog
         role="dialog"
         aria-modal="true"
         aria-labelledby="expense-modal-title"
         tabindex="-1"
         @keydown.escape.window="createModalOpen = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="dialog-overlay fixed inset-0 bg-black/40 backdrop-blur-[2px] z-50 flex items-center justify-center p-4"
         style="display: none;">
        <div class="dialog-panel bg-white border border-cocoa-100 rounded-xl max-w-lg w-full p-6 shadow-xl"
             x-show="createModalOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-[0.98] -translate-y-1"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-[0.98] -translate-y-1"
             @click.away="createModalOpen = false">
            <div class="flex items-center justify-between pb-3 border-b border-cocoa-100/60 mb-4">
                <div>
                    <h2 id="expense-modal-title" class="text-base font-bold text-cocoa-600">Add Expense</h2>
                    <p class="text-xs text-cocoa-400 mt-0.5">Record daily purchases, grocery runs, or shop operational costs.</p>
                </div>
                <button type="button"
                        data-dialog-close
                        @click="createModalOpen = false"
                        aria-label="Close dialog"
                        class="text-cocoa-400 hover:text-cocoa-600 p-1.5 rounded-lg hover:bg-cream-100 transition">
                    <x-icon name="close" class="w-5 h-5" />
                </button>
            </div>

            <form action="{{ route('expenses.store') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="_form" value="create">
                <input type="hidden" name="submission_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

                <div>
                    <label for="modal-expense-description" class="block text-xs font-semibold text-cocoa-500 mb-1.5">Description <span class="text-red-500">*</span></label>
                    <input id="modal-expense-description"
                           name="description"
                           type="text"
                           required
                           maxlength="255"
                           placeholder="e.g. Puregold weekly groceries, Cake boxes 8x8, Mixer parts"
                           value="{{ old('description') }}"
                           class="w-full text-sm rounded-lg border border-cocoa-100 bg-white px-3 py-2 text-cocoa-600 focus:border-cocoa-300 focus:ring-1 focus:ring-cocoa-300">
                    @error('description')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="modal-expense-category" class="block text-xs font-semibold text-cocoa-500 mb-1.5">Category <span class="text-red-500">*</span></label>
                        <select id="modal-expense-category"
                                name="category"
                                required
                                class="w-full text-sm rounded-lg border border-cocoa-100 bg-white px-3 py-2 text-cocoa-600 focus:border-cocoa-300 focus:ring-1 focus:ring-cocoa-300">
                            @foreach([
                                'ingredients' => 'Groceries',
                                'packaging' => 'Packaging',
                                'equipment' => 'Equipment',
                                'miscellaneous' => 'Miscellaneous'
                            ] as $catKey => $catLabel)
                                <option value="{{ $catKey }}" @selected(old('category', 'ingredients') === $catKey)>{{ $catLabel }}</option>
                            @endforeach
                        </select>
                        @error('category')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="modal-expense-amount" class="block text-xs font-semibold text-cocoa-500 mb-1.5">Amount (₱) <span class="text-red-500">*</span></label>
                        <input id="modal-expense-amount"
                               name="amount"
                               type="number"
                               inputmode="decimal"
                               min="0.01"
                               max="99999999.99"
                               step="0.01"
                               required
                               placeholder="0.00"
                               value="{{ old('amount') }}"
                               class="w-full text-sm rounded-lg border border-cocoa-100 bg-white px-3 py-2 text-cocoa-600 focus:border-cocoa-300 focus:ring-1 focus:ring-cocoa-300">
                        @error('amount')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="modal-expense-date" class="block text-xs font-semibold text-cocoa-500 mb-1.5">Expense date <span class="text-red-500">*</span></label>
                    <input id="modal-expense-date"
                           name="expense_date"
                           type="date"
                           required
                           value="{{ old('expense_date', now()->toDateString()) }}"
                           class="w-full text-sm rounded-lg border border-cocoa-100 bg-white px-3 py-2 text-cocoa-600 focus:border-cocoa-300 focus:ring-1 focus:ring-cocoa-300">
                    @error('expense_date')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-cocoa-100/60 mt-4">
                    <button type="button"
                            data-dialog-close
                            @click="createModalOpen = false"
                            class="ui-button quiet">
                        Cancel
                    </button>
                    <button type="submit"
                            class="ui-button primary">
                        Record expense
                    </button>
                </div>
            </form>
        </div>
    </div>

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
