@extends('layouts.admin')
@section('title', 'Expenses')
@section('content')
<div class="workspace">
    <header class="workspace-heading"><div><h1>Expenses</h1><p>Daily purchases and business costs.</p></div><div class="workspace-actions"><a class="ui-button" href="{{ route('expenses.history') }}">Audit history</a><a class="ui-button primary" href="{{ route('expenses.create') }}">Add expense</a></div></header>
    <form method="GET" class="workspace-filters" aria-label="Filter expenses">
        <div class="filter-search"><label for="expense-q">Search</label><input id="expense-q" name="q" value="{{ request('q') }}" placeholder="Description"></div>
        <div><label for="expense-category">Category</label><select id="expense-category" name="category"><option value="">All categories</option>@foreach(['ingredients','packaging','equipment','miscellaneous'] as $category)<option @selected(request('category') === $category) value="{{ $category }}">{{ ucfirst($category) }}</option>@endforeach</select></div>
        <div><label for="expense-from">From</label><input id="expense-from" type="date" name="start_date" value="{{ request('start_date') }}"></div>
        <div><label for="expense-to">To</label><input id="expense-to" type="date" name="end_date" value="{{ request('end_date') }}"></div>
        <div><label for="expense-status">Status</label><select id="expense-status" name="status">@foreach(['active'=>'Active','voided'=>'Voided','all'=>'All records'] as $value=>$label)<option value="{{ $value }}" @selected(request('status','active') === $value)>{{ $label }}</option>@endforeach</select></div>
        <input type="hidden" name="sort" value="{{ request('sort','expense_date') }}"><input type="hidden" name="direction" value="{{ request('direction','desc') }}">
        <button class="ui-button" type="submit">Apply</button><a class="ui-button quiet" href="{{ route('expenses.index') }}">Reset</a>
    </form>
    <div class="workspace-summary"><span>{{ number_format($expenses->total()) }} matching records</span><p>Active total for these filters <strong>₱{{ number_format($totalExpenses, 2) }}</strong></p></div>
    <div class="workspace-table table-scroll" role="region" aria-label="Expenses table" tabindex="0"><table>
        <thead><tr><th><x-sort-heading column="expense_date" label="Expense date" /></th><th><x-sort-heading column="description" label="Description" /></th><th><x-sort-heading column="category" label="Category" /></th><th class="numeric"><x-sort-heading column="amount" label="Amount" /></th><th>Recorded by</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>@forelse($expenses as $expense)<tr>
            <td class="nowrap">{{ $expense->expense_date->format('M d, Y') }}</td><td><a class="record-link" href="{{ route('expenses.show',$expense) }}">{{ $expense->description }}</a></td><td>{{ ucfirst($expense->category) }}</td><td class="numeric">₱{{ number_format($expense->amount,2) }}</td><td>{{ $expense->user->full_name }}</td><td><span class="status {{ $expense->trashed() ? 'status-danger' : 'status-success' }}">{{ $expense->trashed() ? 'Voided' : 'Active' }}</span></td>
            <td><details class="row-actions"><summary>Actions</summary><div><a href="{{ route('expenses.show',$expense) }}">View</a>@unless($expense->trashed())<a href="{{ route('expenses.edit',$expense) }}">Edit</a><a href="{{ route('expenses.show',$expense) }}#delete-expense">Delete</a>@endunless<a href="{{ route('expenses.history',['expense_id'=>$expense->id]) }}">History</a></div></details></td>
        </tr>@empty<tr><td colspan="7" class="empty-state">No expenses match these filters. <a href="{{ route('expenses.index') }}">Clear filters</a> or add an expense.</td></tr>@endforelse</tbody>
    </table></div><x-workspace-pagination :records="$expenses" />
</div>
@endsection
