@extends('layouts.admin')
@section('title', $supply->exists ? 'Edit supply' : 'Add supply')
@section('content')
<div class="workspace form-workspace"><header class="workspace-heading"><div><a class="back-link" href="{{ route('supplies.index') }}">← Inventory</a><h1>{{ $supply->exists ? 'Edit supply' : 'Add supply' }}</h1></div></header>
<form class="workspace-form" data-safe-form action="{{ $supply->exists ? route('supplies.update',$supply) : route('supplies.store') }}" method="POST">@csrf @if($supply->exists) @method('PATCH') @endif
<div><label for="supply_name">Supply name</label><input id="supply_name" name="supply_name" required maxlength="255" value="{{ old('supply_name',$supply->supply_name) }}"></div>
<div class="form-grid"><div><label for="category">Category</label><select id="category" name="category">@foreach(['ingredients','packaging'] as $category)<option @selected(old('category',$supply->category)===$category) value="{{ $category }}">{{ ucfirst($category) }}</option>@endforeach</select></div><div><label for="unit">Stock unit</label><input id="unit" name="unit" required maxlength="50" value="{{ old('unit',$supply->unit) }}" @readonly($supply->exists) placeholder="kg, litre, piece…"><p class="form-hint">All movements use this unit. Bags and kilograms require separate supplies or an explicit conversion outside this system.</p></div>
@unless($supply->exists)<div><label for="current_quantity">Opening quantity</label><input id="current_quantity" name="current_quantity" type="number" min="0" step="0.01" max="99999999.99" required value="{{ old('current_quantity',0) }}"><p class="form-hint">Recorded as an opening balance.</p></div>@endunless
<div><label for="reorder_level">Reorder level</label><input id="reorder_level" name="reorder_level" type="number" min="0" step="0.01" max="99999999.99" required value="{{ old('reorder_level',$supply->reorder_level ?? 0) }}"></div></div>
<input type="hidden" name="is_active" value="0"><label class="check-label"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$supply->is_active ?? true))> Active supply</label><div class="form-actions"><a class="ui-button" href="{{ route('supplies.index') }}">Cancel</a><button class="ui-button primary">Save supply</button><span data-submit-status role="status"></span></div>
</form></div>
@endsection
