@extends('layouts.admin')
@section('title', 'Legacy stock movement')
@section('content')
<div class="workspace inventory-workspace"><header class="workspace-heading"><div><a class="back-link" href="{{ route('inventory.history') }}">← Movement history</a><h1>Legacy movement #{{ $movement->id }}</h1><p>Original history retained. Before and after quantities were not recorded at the time.</p></div></header>
@include('admin.supplies.movement-table', ['movements' => collect([$movement])])
@if($movement->reversal)<p class="workspace-panel">Reversed by <a class="record-link" href="{{ route('inventory.show', $movement->reversal->operation) }}">operation #{{ $movement->reversal->inventory_operation_id }}</a>.</p>
@else<details class="workspace-panel" @if($errors->any()) open @endif><summary>Correct this movement</summary><form class="workspace-form compact" data-safe-form method="POST" action="{{ route('inventory.movement.reverse',$movement) }}">@csrf<input type="hidden" name="submission_key" value="{{ old('submission_key',(string) Str::uuid()) }}"><p>The linked reversal applies the opposite quantity to current stock. It preserves this record and is rejected if stock would become negative.</p>@include('admin.supplies.entry-reconciliation', ['originalMovements'=>collect([$movement])])<div><label for="notes">Reason for correction</label><textarea id="notes" name="notes" rows="2" maxlength="2000" required>{{ old('notes') }}</textarea></div><div class="form-actions"><button class="ui-button danger">Post linked reversal</button><span data-submit-status role="status"></span></div></form></details>@endif
</div>
@endsection
