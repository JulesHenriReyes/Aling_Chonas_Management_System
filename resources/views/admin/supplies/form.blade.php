@extends('layouts.admin')
@section('title', $supply->exists ? 'Edit supply' : 'Add supply')
@section('content')
<script src="{{ asset('js/inventory-workspace.js') }}?v={{ filemtime(public_path('js/inventory-workspace.js')) }}"></script>
<div class="workspace form-workspace"><header class="workspace-heading"><div><a class="back-link" href="{{ route('supplies.index') }}">← Inventory</a><h1>{{ $supply->exists ? 'Edit supply' : 'Add supply' }}</h1></div></header>
<form class="workspace-form" data-safe-form x-data="supplyDefinition(@js(['supply_name'=>old('supply_name',$supply->supply_name ?? ''),'category'=>old('category',$supply->category ?? 'ingredients'),'unit'=>old('unit',$supply->unit ?? 'kg')]))" action="{{ $supply->exists ? route('supplies.update',$supply) : route('supplies.store') }}" method="POST">@csrf @if($supply->exists) @method('PATCH') @endif
@unless($supply->exists)<p class="form-hint">Create each supply once. Use Stock in to add quantities whenever you buy more.</p><input type="hidden" name="current_quantity" value="0">@endunless
@include('admin.supplies.definition-fields',['prefix'=>'supply'])
<div class="form-grid">
<div><label for="reorder_level">Reorder level</label><input id="reorder_level" name="reorder_level" type="number" min="0" step="0.01" max="99999999.99" required value="{{ old('reorder_level',$supply->reorder_level ?? 0) }}"></div></div>
<input type="hidden" name="is_active" value="0"><label class="check-label"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$supply->is_active ?? true))> Active supply</label><div class="form-actions"><a class="ui-button" href="{{ route('supplies.index') }}">Cancel</a>@unless($supply->exists)<button class="ui-button primary" name="next" value="stock_in">Save & stock in</button>@endunless<button class="ui-button {{ $supply->exists ? 'primary' : '' }}">{{ $supply->exists ? 'Save supply' : 'Save supply only' }}</button><span data-submit-status role="status"></span></div>
</form></div>
@endsection
