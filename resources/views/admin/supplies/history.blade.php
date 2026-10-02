@extends('layouts.admin')
@section('title', 'Movement history')
@section('content')
<div class="workspace"><header class="workspace-heading"><div><a class="back-link" href="{{ route('supplies.index') }}">← Inventory</a><h1>Movement history</h1><p>Every posted stock change, grouped by operation.</p></div></header>
<form class="workspace-filters" method="GET"><div class="filter-search"><label for="history-q">Supply name</label><input id="history-q" name="q" value="{{ request('q') }}"></div><div><label for="history-type">Operation</label><select id="history-type" name="type"><option value="">All operations</option>@foreach(['receipt','usage','waste','stocktake','adjustment','reversal'] as $type)<option @selected(request('type')===$type) value="{{ $type }}">{{ ucfirst($type) }}</option>@endforeach</select></div>@if(request('supply_id'))<input type="hidden" name="supply_id" value="{{ request('supply_id') }}">@endif<button class="ui-button">Apply</button><a class="ui-button quiet" href="{{ route('inventory.history') }}">Reset</a></form>
@include('admin.supplies.movement-table')<x-workspace-pagination :records="$movements" /></div>
@endsection
