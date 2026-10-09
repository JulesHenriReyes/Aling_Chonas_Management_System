@extends('layouts.admin')
@section('title', $expense->exists ? 'Edit expense' : 'Add expense')
@section('content')
<div class="workspace form-workspace">
    <header class="workspace-heading">
        <div>
            <a class="back-link" data-cancel href="{{ route('expenses.index') }}">← Expenses</a>
            <h1>{{ $expense->exists ? 'Edit expense' : 'Add expense' }}</h1>
        </div>
    </header>
    <form class="workspace-form" data-safe-form method="POST" action="{{ $expense->exists ? route('expenses.update', $expense) : route('expenses.store') }}">
        @csrf
        @if($expense->exists)
            @method('PATCH')
            <input type="hidden" name="version" value="{{ old('version', $expense->version) }}">
        @else
            <input type="hidden" name="submission_key" value="{{ old('submission_key', (string) Str::uuid()) }}">
        @endif
        <div>
            <label for="description">Description</label>
            <input id="description" name="description" maxlength="255" required placeholder="e.g. Puregold weekly groceries, Cake boxes 8x8, Mixer parts" value="{{ old('description', $expense->description) }}">
        </div>
        <div class="form-grid">
            <div>
                <label for="category">Category</label>
                <select id="category" name="category" required>
                    @foreach(['ingredients' => 'Groceries', 'packaging' => 'Packaging', 'equipment' => 'Equipment', 'miscellaneous' => 'Miscellaneous'] as $catKey => $catLabel)
                        <option @selected(old('category', $expense->category) === $catKey) value="{{ $catKey }}">{{ $catLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="amount">Amount (₱)</label>
                <input id="amount" name="amount" type="number" inputmode="decimal" min="0.01" max="99999999.99" step="0.01" required value="{{ old('amount', $expense->amount) }}">
            </div>
            <div>
                <label for="expense_date">Expense date</label>
                <input id="expense_date" name="expense_date" type="date" required value="{{ old('expense_date', $expense->expense_date?->toDateString() ?? now()->toDateString()) }}">
            </div>
        </div>
        @if($expense->exists)
            <div>
                <label for="reason">Reason for edit (optional)</label>
                <textarea id="reason" name="reason" rows="2" maxlength="1000">{{ old('reason') }}</textarea>
            </div>
            <p class="form-hint">Originally recorded by {{ $expense->user?->full_name ?? 'Staff' }}. Changes are saved in audit history.</p>
        @endif
        <div class="form-actions">
            <a class="ui-button" data-cancel href="{{ route('expenses.index') }}">Cancel</a>
            <button class="ui-button primary" type="submit">{{ $expense->exists ? 'Save changes' : 'Record expense' }}</button>
            <span data-submit-status role="status"></span>
        </div>
    </form>
</div>
@endsection
