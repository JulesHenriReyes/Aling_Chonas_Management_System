@extends('layouts.admin')
@section('title', 'Supply details')
@section('content')
<div class="workspace inventory-workspace" x-data="{
    showEditModal: {{ $errors->any() && old('_form') === 'edit_supply' ? 'true' : 'false' }},
    editSupply: {
        id: {{ $supply->id }},
        supply_name: @js(old('_form') === 'edit_supply' ? old('supply_name', $supply->supply_name) : $supply->supply_name),
        category: @js(old('_form') === 'edit_supply' ? old('category', $supply->category) : $supply->category),
        unit: @js($supply->unit),
        reorder_level: @js(old('_form') === 'edit_supply' ? old('reorder_level', number_format($supply->reorder_level, 2, '.', '')) : number_format($supply->reorder_level, 2, '.', '')),
        is_active: {{ old('_form') === 'edit_supply' ? (old('is_active', $supply->is_active) ? 'true' : 'false') : ($supply->is_active ? 'true' : 'false') }},
        update_url: @js(route('supplies.update', $supply))
    }
}"><header class="workspace-heading"><div><a class="back-link" href="{{ route('supplies.index') }}">← Inventory</a><h1>{{ $supply->supply_name }}</h1><p>{{ ucfirst($supply->category) }} · {{ $supply->is_active ? 'Active' : 'Inactive' }}</p></div><div class="workspace-actions"><a class="ui-button primary" href="{{ route('inventory.create', ['type' => 'receipt', 'supply_id' => $supply->id]) }}"><x-icon name="plus" /> Stock in</a><a class="ui-button" href="{{ route('inventory.create', ['type' => 'usage', 'supply_id' => $supply->id]) }}">Stock out</a><a class="ui-button" href="{{ route('supplies.edit',$supply) }}" @click.prevent="showEditModal = true">Edit supply</a></div></header>
<section class="workspace-panel"><dl class="detail-grid"><div><dt>On hand now</dt><dd>{{ number_format($supply->current_quantity,2) }} {{ $supply->unit }}</dd></div><div><dt>Usable stock</dt><dd>{{ number_format($supply->usable_quantity,2) }} {{ $supply->unit }}</dd></div><div><dt>Reorder level</dt><dd>{{ number_format($supply->reorder_level,2) }} {{ $supply->unit }}</dd></div><div><dt>Opening balance</dt><dd>{{ $baseline ? number_format($baseline->opening_quantity,2).' '.$baseline->unit : 'Not established' }}</dd></div><div><dt>Net recorded movements</dt><dd>{{ number_format($netMovement,2) }} {{ $supply->unit }}</dd></div></dl>
@if($baseline)<p class="form-hint">{{ $baseline->source === 'legacy_reconciliation' ? 'Legacy reconciliation baseline' : 'Opening balance' }} established {{ $baseline->established_at }}. Opening balance + net movements = {{ number_format($baseline->opening_quantity + $netMovement,2) }} {{ $supply->unit }}.@if($baseline->source === 'legacy_reconciliation') Historical balances before this baseline cannot be verified; no historical receipt has been invented.@endif</p>@else<p>No baseline exists yet; the next recorded movement will establish a reconciliation baseline.</p>@endif</section>
<section class="workspace-panel"><h2>Stock entries</h2>@forelse($supply->stockEntries as $entry)<p class="form-hint"><a class="record-link" href="{{ route('inventory.history',['supply_id'=>$supply->id,'stock_entry_id'=>$entry->id]) }}">Entry #{{ $entry->id }}</a> · {{ $entry->remaining_quantity }} {{ $supply->unit }} remaining · {{ $entry->expiry_date?->format('M j, Y') ?? ($supply->category === 'packaging' ? 'No expiry applicable' : 'Expiry unknown') }} · {{ str_replace('_',' ',$entry->source) }}</p>@empty<p>No stock entries yet.</p>@endforelse</section><h2>Movement history</h2>@include('admin.supplies.movement-table')<x-workspace-pagination :records="$movements" />
@include('admin.supplies.edit-modal')
</div>
@endsection
