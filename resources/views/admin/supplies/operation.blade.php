@extends('layouts.admin')
@section('title', 'Stock operation')
@section('content')
<div class="workspace"><header class="workspace-heading"><div><a class="back-link" href="{{ route('inventory.history') }}">← Movement history</a><h1>{{ ucfirst($operation->type) }} #{{ $operation->id }}</h1><p>Posted {{ $operation->created_at->format('M d, Y H:i:s') }} by {{ $operation->user->full_name }}</p></div><a class="ui-button" href="{{ route('supplies.index') }}">Inventory</a></header>
<section class="workspace-panel"><dl class="detail-grid"><div><dt>Effective date</dt><dd>{{ $operation->operation_date->format('M d, Y') }}</dd></div><div><dt>Supplier</dt><dd>{{ $operation->supplier ?? '—' }}</dd></div><div><dt>Delivery reference</dt><dd>{{ $operation->delivery_reference ?? '—' }}</dd></div><div><dt>Notes / reason</dt><dd>{{ $operation->notes ?? '—' }}</dd></div></dl>
@if($operation->original)<p>Reversal of <a class="record-link" href="{{ route('inventory.show',$operation->original) }}">operation #{{ $operation->original->id }}</a>.</p>@endif
@if($operation->reversal)<p class="notice-danger">Reversed by <a class="record-link" href="{{ route('inventory.show',$operation->reversal) }}">operation #{{ $operation->reversal->id }}</a>.</p>@endif</section>
@include('admin.supplies.movement-table',['movements'=>$operation->movements])
<p class="form-hint">This operation changes stock only. Record any actual purchase separately in Expenses.</p>
@if(!$operation->reversal && !$operation->reversal_of_id)<details class="workspace-panel" @if($errors->any()) open @endif><summary>Correct this operation</summary><form class="workspace-form compact" data-safe-form method="POST" action="{{ route('inventory.reverse',$operation) }}">@csrf<input type="hidden" name="submission_key" value="{{ old('submission_key',(string) Str::uuid()) }}"><p>A linked reversal undoes all rows while retaining this history. Then post the corrected receipt or count. Reversal is rejected if it would make stock negative.</p><div><label for="notes">Reason for correction</label><textarea id="notes" name="notes" rows="2" maxlength="2000" required>{{ old('notes') }}</textarea></div><div class="form-actions"><button class="ui-button danger">Post linked reversal</button><span data-submit-status role="status"></span></div></form></details>@endif
</div>
@endsection
