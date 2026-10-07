@extends('layouts.admin')
@section('title', 'Verify opening stock')
@section('content')
<script src="{{ asset('js/inventory-workspace.js') }}?v={{ filemtime(public_path('js/inventory-workspace.js')) }}"></script>
@php
    $review = []; $saved = collect(old('lines', []))->keyBy('stock_entry_id');
    foreach ($supplies as $supply) foreach ($supply->stockEntries as $entry) {
        $old = $saved->get($entry->id);
        $review[] = ['supply_id'=>$supply->id, 'stock_entry_id'=>$entry->id, 'name'=>$supply->supply_name, 'unit'=>$supply->unit, 'remaining_quantity'=>$entry->remaining_quantity, 'expected_version'=>$old['expected_version'] ?? $supply->stock_version, 'selected'=>(bool)$old, 'splits'=>$old['splits'] ?? [['quantity'=>$entry->remaining_quantity,'expiry_date'=>'']]];
    }
@endphp
<div class="workspace inventory-workspace" x-data="{ rows: @js($review), total(row) { return row.splits.reduce((total, part) => total + Math.round(Number(part.quantity || 0)*100),0)/100; } }">
    <header class="workspace-heading"><div><a class="back-link" href="{{ route('supplies.index') }}">← Inventory</a><h1>Verify opening stock</h1><p>Confirm the dates on existing stock. This keeps the quantity on hand unchanged.</p></div></header>
    <p class="form-hint">Select entries you have checked. Split a quantity when its supplies have different expiry dates. Do not stock in these quantities again.</p>
    @if($supplies->isEmpty())<section class="workspace-panel">No opening stock awaits verification.</section>@else
    <form class="workspace-form" method="POST" action="{{ route('inventory.verify.store') }}" data-safe-form>
        @csrf
        <input type="hidden" name="submission_key" value="{{ old('submission_key',(string) Str::uuid()) }}">
        <template x-for="(row,index) in rows" :key="row.stock_entry_id">
            <section class="inventory-verification-row">
                <label class="inventory-review-select check-label"><input type="checkbox" x-model="row.selected"><span><strong x-text="row.name"></strong><span class="inventory-row-meta" x-text="'Opening entry #'+row.stock_entry_id+' · '+row.remaining_quantity+' '+row.unit+' to verify'"></span></span></label>
                <input type="hidden" :name="'lines['+index+'][supply_id]'" :value="row.supply_id" :disabled="!row.selected">
                <input type="hidden" :name="'lines['+index+'][stock_entry_id]'" :value="row.stock_entry_id" :disabled="!row.selected">
                <input type="hidden" :name="'lines['+index+'][expected_version]'" :value="row.expected_version" :disabled="!row.selected">
                <div x-show="row.selected" x-cloak>
                    <template x-for="(part,partIndex) in row.splits" :key="partIndex"><div class="inventory-verify-split">
                        <div><label :for="'verify-quantity-'+index+'-'+partIndex">Quantity (<span x-text="row.unit"></span>)</label><input type="number" :id="'verify-quantity-'+index+'-'+partIndex" :name="'lines['+index+'][splits]['+partIndex+'][quantity]'" :disabled="!row.selected" :required="row.selected" min="0.01" max="99999999.99" step="0.01" inputmode="decimal" x-model="part.quantity"></div>
                        <div><label :for="'verify-expiry-'+index+'-'+partIndex">Verified expiration date</label><input type="date" :id="'verify-expiry-'+index+'-'+partIndex" :name="'lines['+index+'][splits]['+partIndex+'][expiry_date]'" :disabled="!row.selected" :required="row.selected" x-model="part.expiry_date"></div>
                        <button type="button" class="ui-button quiet" x-show="row.splits.length > 1" @click="row.splits.splice(partIndex,1)">Remove portion</button>
                    </div></template>
                    <p class="form-hint" :class="Math.round(total(row)*100) !== Math.round(Number(row.remaining_quantity)*100) ? 'field-error' : ''" x-text="total(row).toFixed(2)+' / '+row.remaining_quantity+' '+row.unit+' assigned'"></p>
                    <button type="button" class="ui-button" @click="row.splits.push({quantity:'',expiry_date:''})">Split across another date</button>
                </div>
            </section>
        </template>
        <div><label for="verification-notes">Verification reason / reference</label><textarea id="verification-notes" name="notes" required rows="2" maxlength="2000">{{ old('notes') }}</textarea></div>
        <div class="form-actions"><a class="ui-button" data-cancel @click="window.__suppressUnload = true" href="{{ route('supplies.index') }}">Cancel</a><button class="ui-button primary" :disabled="!rows.some(row => row.selected)">Verify selected stock</button><span data-submit-status role="status"></span></div>
    </form>
    @endif
</div>
@endsection
