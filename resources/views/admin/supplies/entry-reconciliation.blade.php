@foreach($originalMovements as $originalMovement)
    @if($originalMovement->supply->category === 'ingredients' && $originalMovement->allocations->isEmpty())
        @if(auth()->user()->isOwner())
            @if($originalMovement->transaction_type !== 'stock_out' && $originalMovement->quantity > 0)
                <fieldset class="inventory-verification-row"><legend>Reconcile {{ $originalMovement->supply->supply_name }} · movement #{{ $originalMovement->id }}</legend>
                <p class="form-hint">This historical movement has no entry allocations. Select exactly {{ number_format($originalMovement->quantity,2) }} {{ $originalMovement->supply->unit }} to remove. Verify the physical stock before correcting.</p>
                @foreach($originalMovement->supply->stockEntries->where('remaining_quantity','>',0) as $entry)
                    <div class="inventory-entry-input"><label for="reconcile-{{ $originalMovement->id }}-{{ $entry->id }}">Entry #{{ $entry->id }} · {{ $entry->expiry_date?->format('M j, Y') ?? 'Expiry unknown' }} · {{ $entry->remaining_quantity }} remaining</label><input type="hidden" name="reconciliation[{{ $originalMovement->id }}][{{ $loop->index }}][stock_entry_id]" value="{{ $entry->id }}"><input id="reconcile-{{ $originalMovement->id }}-{{ $entry->id }}" type="number" name="reconciliation[{{ $originalMovement->id }}][{{ $loop->index }}][quantity]" min="0" max="{{ $entry->remaining_quantity }}" step="0.01" inputmode="decimal" required value="{{ old('reconciliation.'.$originalMovement->id.'.'.$loop->index.'.quantity',0) }}"></div>
                @endforeach
                </fieldset>
            @else
                <p class="form-hint">Restoring this legacy ingredient stock creates an unknown-expiry reconciliation entry. Verify its date before baking.</p>
            @endif
        @else
            <p class="field-error">The Owner must reconcile this ingredient movement because its original entry allocations are unavailable.</p>
        @endif
    @endif
@endforeach
