@extends('layouts.admin')
@section('title', $type === 'receipt' ? 'Stock in' : 'Stock out')
@section('content')
<script src="{{ asset('js/inventory-workspace.js') }}?v={{ filemtime(public_path('js/inventory-workspace.js')) }}"></script>
<div class="workspace" x-data="stockOperation(@js($type), @js($initialLines), @js(route('supplies.lookup')), @js(route('supplies.store')), @js(csrf_token()))">
<header class="workspace-heading"><div><a class="back-link" href="{{ route('supplies.index') }}">← Inventory</a><h1>{{ $type === 'receipt' ? 'Stock in' : 'Stock out' }}</h1><p>Add supplies and review the stock changes before saving.</p></div></header>
<form class="workspace-form" x-ref="stockForm" data-safe-form action="{{ route('inventory.store') }}" method="POST" @submit="if (!lines.length) { $event.preventDefault(); error = 'Add at least one supply.'; $refs.search.focus(); }">
@csrf<input type="hidden" name="submission_key" value="{{ old('submission_key',(string) Str::uuid()) }}">
<div class="form-grid">
@if(in_array($type,['usage','waste','stocktake']))<div><label for="operation-type">How did stock change?</label><select id="operation-type" name="type" :value="type" @change="changeType($event.target.value)"><option value="usage">Used for baking</option><option value="waste">Waste / spoilage</option><option value="stocktake">Count remaining stock</option></select><p class="form-hint" x-show="type === 'stocktake'" x-cloak>A stock count sets the quantity on hand. It can increase or decrease recorded stock.</p></div>@else<input type="hidden" name="type" :value="type">@endif
<div><label for="operation_date">{{ $type === 'receipt' ? 'Stock-in date' : 'Effective date' }}</label><input type="date" id="operation_date" name="operation_date" required value="{{ old('operation_date',now()->toDateString()) }}"></div>
@if($type === 'receipt')<div><label for="supplier">Supplier (optional)</label><input id="supplier" name="supplier" maxlength="255" value="{{ old('supplier') }}"></div>@endif
</div>
<div class="supply-search" @click.outside="pickerOpen = false" @keydown.escape.prevent.stop="$refs.search.focus(); pickerOpen = false">
<label for="find-supply">Find a supply</label>
<div class="supply-picker-control"><input x-ref="search" id="find-supply" x-model="search" @focus="openPicker()" @input="pickerOpen = true" @input.debounce.250ms="lookup()" @keydown.arrow-down.prevent="focusResult()" placeholder="Type a name or browse supplies" autocomplete="off" maxlength="255" aria-controls="supply-search-results"><button type="button" class="ui-button supply-picker-toggle" @click="togglePicker()" aria-label="Toggle supply dropdown" aria-controls="supply-search-results" :aria-expanded="pickerOpen"><x-icon path="m6 9 6 6 6-6" /></button></div>
<div class="supply-picker-panel" x-ref="panel" x-show="pickerOpen" x-cloak>
<p class="supply-picker-status" role="status" x-show="loading">Searching…</p>
<div class="supply-picker-empty" x-show="!loading && searched && !results.length">
    <p class="supply-picker-empty-text">
        <span x-show="search.trim()">No supplies found matching <strong x-text="`“${search.trim()}”`"></strong>.</span>
        <span x-show="!search.trim()">No matching supplies found.</span>
    </p>
    @if($type === 'receipt')
    <button type="button" class="supply-picker-create-action" @click="openNewSupply()">
        <x-icon name="plus" />
        <span x-text="search.trim() ? `Create “${search.trim()}” as new supply` : 'Create a new supply'"></span>
    </button>
    @endif
</div>
<ul x-ref="results" id="supply-search-results" class="supply-results" aria-label="Available supplies" x-show="!loading && results.length"><template x-for="supply in results" :key="supply.id"><li><button type="button" @click="add(supply, $event)" :disabled="lines.some(line => Number(line.supply_id) === supply.id)"><span class="supply-result-name" x-text="supply.supply_name"></span><span class="supply-result-stock" x-text="quantity(supply.current_quantity, supply.unit) + ' on hand'"></span><span class="supply-result-action" x-text="lines.some(line => Number(line.supply_id) === supply.id) ? 'Added' : '+ Add'"></span></button></li></template></ul>
<div class="supply-picker-footer" x-show="!loading && results.length">
    <p class="supply-picker-footnote">Select several supplies, then enter quantities below.</p>
    @if($type === 'receipt')
    <button type="button" class="supply-picker-footer-create" @click="openNewSupply()">
        <x-icon name="plus" />
        <span x-text="search.trim() ? `Not in list? Add “${search.trim()}”` : 'Add new supply'"></span>
    </button>
    @endif
</div>
</div><p class="form-hint" role="status" x-show="notice" x-text="notice" x-cloak></p></div>
<p class="field-error" role="alert" x-show="error" x-text="error" x-cloak></p>
<div class="workspace-table table-scroll" role="region" aria-label="Stock operation rows" tabindex="0"><table class="batch-table"><colgroup><col class="batch-supply-col"><col class="batch-stock-col"><col class="batch-quantity-col"><col class="batch-change-col"><col class="batch-stock-col"><col class="batch-actions-col"></colgroup><thead><tr><th>Supply</th><th class="numeric"><span class="inline-flex items-center gap-1 justify-end">On hand <x-tooltip text="Current recorded on-hand quantity in the system. Use reload if another staff member updated inventory." align="right" /></span></th><th x-text="type === 'stocktake' ? 'Counted quantity' : 'Quantity'"></th><th class="numeric">Change</th><th class="numeric">After saving</th><th>Actions</th></tr></thead><tbody>
<template x-for="(line,index) in lines" :key="line.supply_id"><tr><td><strong x-text="line.name"></strong><input type="hidden" :name="'lines['+index+'][supply_id]'" :value="line.supply_id"><input type="hidden" :name="'lines['+index+'][expected_version]'" :value="line.expected_version"></td><td class="numeric" x-text="quantity(line.current_quantity,line.unit)"></td><td><div class="stock-quantity-input"><input :id="'stock-quantity-'+line.supply_id" :name="'lines['+index+'][quantity]'" :aria-label="line.name + ' quantity in ' + line.unit" type="number" inputmode="decimal" :min="type === 'stocktake' ? 0 : 0.01" max="99999999.99" step="0.01" required x-model="line.quantity"><span x-text="line.unit" aria-hidden="true"></span></div></td><td class="numeric" x-text="quantity(delta(line),line.unit,true)"></td><td class="numeric" :class="after(line) < 0 ? 'field-error' : ''" x-text="quantity(after(line),line.unit)"></td><td><div class="stock-row-actions"><button type="button" class="ui-button quiet" :aria-label="'Remove '+line.name" @click="lines.splice(index,1)">Remove</button><span x-show="type==='stocktake'" class="inline-flex items-center gap-1"><button type="button" class="ui-button quiet" @click="refreshLine(line)" :aria-label="'Reload current stock for '+line.name">Reload stock</button><x-tooltip text="Fetches the latest recorded quantity and lock version from the server to prevent concurrency conflicts with other staff." align="right" /></span></div></td></tr></template>
<tr x-show="!lines.length"><td colspan="6" class="empty-state">Choose supplies above to add the first row.</td></tr></tbody></table></div>
<div><label for="notes" x-text="['waste','stocktake'].includes(type) ? 'Reason / explanation' : 'Notes (optional)'"></label><textarea id="notes" name="notes" rows="2" maxlength="2000" :required="['waste','stocktake'].includes(type)">{{ old('notes') }}</textarea></div>
<div class="form-actions"><span x-text="lines.length + ' supplies · all rows save together'"></span><a class="ui-button" href="{{ route('supplies.index') }}">Cancel</a><button class="ui-button primary" :disabled="!lines.length" type="submit" x-text="type === 'receipt' ? 'Save stock in' : type === 'stocktake' ? 'Save stock count' : 'Save stock out'">{{ $type === 'receipt' ? 'Save stock in' : 'Save stock out' }}</button><span role="status" data-submit-status></span></div>
</form>
@if($type === 'receipt')
<dialog x-ref="newSupplyDialog" class="supply-create-dialog" aria-labelledby="new-supply-title" @cancel="if (creating) $event.preventDefault()">
<form x-ref="newSupplyForm" class="workspace-form" @submit.prevent="createSupply($event)">
<div><h2 id="new-supply-title">Add a new supply</h2><p class="form-hint">Create it once with zero stock. Its quantity goes in the Stock in form.</p></div>
@csrf<input type="hidden" name="current_quantity" value="0"><input type="hidden" name="is_active" value="1">
<div class="supply-definition-fields" data-supply-definition x-data="supplyDefinition(@js(['supply_name'=>'','category'=>'ingredients','unit'=>'kg']))" @reset-definition="resetDefinition($event.detail)">
@include('admin.supplies.definition-fields',['prefix'=>'new','supply'=>new \App\Models\Supply])
</div>
<div><label for="new-reorder-level">Reorder level</label><input id="new-reorder-level" name="reorder_level" type="number" min="0" max="99999999.99" step="0.01" required value="0"><p class="form-hint">Low-stock threshold, in the selected stock unit.</p></div>
<p class="field-error" role="alert" x-show="createError" x-text="createError" x-cloak></p>
<div class="form-actions"><button type="button" class="ui-button" :disabled="creating" @click="$refs.newSupplyDialog.close()">Cancel</button><button type="submit" class="ui-button primary" :disabled="creating" x-text="creating ? 'Adding…' : 'Create & add to stock in'">Create & add to stock in</button></div>
</form></dialog>
@endif
</div>
@endsection
