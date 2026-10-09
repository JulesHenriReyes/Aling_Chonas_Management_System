@extends('layouts.admin')
@section('title', 'Expense details')
@section('content')
<div class="workspace form-workspace">
    <header class="workspace-heading">
        <div>
            <a class="back-link" data-cancel href="{{ route('expenses.index') }}">← Expenses</a>
            <h1>Expense #{{ $expense->id }}</h1>
        </div>
        <div class="workspace-actions">
            <a class="ui-button" href="{{ route('expenses.history', ['expense_id' => $expense->id]) }}">Audit history</a>
            @unless($expense->trashed())
                <a class="ui-button primary" href="{{ route('expenses.edit', $expense) }}">Edit expense</a>
            @endunless
        </div>
    </header>

    <section class="workspace-panel">
        <h2>{{ $expense->description }}</h2>
        <dl class="detail-grid">
            <div><dt>Amount</dt><dd>₱{{ number_format($expense->amount, 2) }}</dd></div>
            <div><dt>Expense date</dt><dd>{{ $expense->expense_date->format('M d, Y') }}</dd></div>
            <div><dt>Category</dt><dd>{{ $expense->category === 'ingredients' ? 'Groceries' : ucfirst($expense->category) }}</dd></div>
            <div><dt>Recorded by</dt><dd>{{ $expense->user->full_name }}</dd></div>
            <div><dt>Status</dt><dd>{{ $expense->trashed() ? 'Voided' : 'Active' }}</dd></div>
            @if($expense->editor)
                <div><dt>Last edited by</dt><dd>{{ $expense->editor->full_name }}</dd></div>
            @endif
        </dl>
        @if($expense->trashed())
            <p class="notice-danger">Voided {{ $expense->deleted_at->format('M d, Y H:i') }} · {{ $expense->deletion_reason }}</p>
        @endif
    </section>

    @unless($expense->trashed())
        <details class="workspace-panel" id="delete-expense" @if(old('reason') || request()->has('delete')) open @endif>
            <summary>Delete expense</summary>
            <form data-safe-form class="workspace-form compact" action="{{ route('expenses.destroy', $expense) }}" method="POST">
                @csrf
                @method('DELETE')
                <input type="hidden" name="version" value="{{ old('version', $expense->version) }}">
                <p>This removes the expense from active totals. Its original record and history remain available.</p>
                <div>
                    <label for="reason">Reason for deletion</label>
                    <textarea id="reason" name="reason" required maxlength="1000" rows="2">{{ old('reason') }}</textarea>
                </div>
                <button class="ui-button danger" type="submit">Delete and retain history</button>
                <span role="status" data-submit-status></span>
            </form>
        </details>
    @endunless
</div>
@endsection
