@extends('layouts.admin')
@section('title', 'Movement history')
@section('content')
<div class="workspace"><header class="workspace-heading"><div><a class="back-link" href="{{ route('supplies.index') }}">← Inventory</a><h1>Movement history</h1><p>Every posted stock change, grouped by operation.</p></div></header>
<form class="workspace-filters" method="GET"><div class="filter-search"><label for="history-q">Supply name</label><input id="history-q" name="q" value="{{ request('q') }}"></div><div><label for="history-type">Operation</label><select id="history-type" name="type"><option value="">All operations</option>@foreach(['receipt','usage','waste','stocktake','adjustment','reversal','expiry_verification'] as $type)<option @selected(request('type')===$type) value="{{ $type }}">{{ ucfirst(str_replace('_',' ',$type)) }}</option>@endforeach</select></div>@if(request('supply_id'))<input type="hidden" name="supply_id" value="{{ request('supply_id') }}">@endif
@if(request('stock_entry_id'))<input type="hidden" name="stock_entry_id" value="{{ request('stock_entry_id') }}">@endif</form>
<div class="workspace-summary"><span>{{ number_format($movements->total()) }} stock movements @if(request()->hasAny(['q', 'type', 'supply_id', 'stock_entry_id'])) · <a class="text-cocoa-500 hover:text-cocoa-700 underline text-xs font-medium ml-1" href="{{ route('inventory.history') }}">Reset filters</a>@endif</span></div>
@include('admin.supplies.movement-table')<x-workspace-pagination :records="$movements" /></div>
@endsection
