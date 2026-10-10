<div class="workspace-table table-scroll" role="region" aria-label="Stock movements" tabindex="0">
    <table class="inventory-movement-table">
        <thead>
            <tr>
                <th>Effective date</th>
                <th>Supply / unit</th>
                <th>Operation</th>
                <th class="numeric">Quantity / batches</th>
                <th class="numeric">Total on hand</th>
                <th>Recorded by</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody x-data="{ expandedMovements: {} }">
            @forelse($movements as $movement)
                @php
                    $unit = $movement->unit ?? $movement->supply->unit;
                    $type = $movement->operation?->type;
                    $quantityLabel = ($movement->transaction_type === 'stock_out' ? '−' : ((float)$movement->quantity > 0 ? '+' : '')).number_format($movement->quantity, 2).' '.$unit;
                @endphp
                <tr>
                    <td class="nowrap">{{ $movement->transaction_date->format('M d, Y') }}<small class="inventory-row-meta">Recorded {{ $movement->created_at->format('M d, Y H:i:s') }}</small></td>
                    <td>
                        <a class="record-link inventory-movement-supply" href="{{ route('supplies.show', $movement->supply) }}"><span>{{ $movement->supply->supply_name }}</span><small>{{ $unit }}</small></a>
                    </td>
                    <td>
                        @if($movement->operation)
                            <a class="record-link" href="{{ route('inventory.show', $movement->operation) }}">#{{ $movement->operation->id }} · {{ ucfirst(str_replace('_',' ',$movement->operation->type)) }}</a>
                        @else
                            <a class="record-link" href="{{ route('inventory.movement', $movement) }}">Legacy {{ str_replace('_', ' ', $movement->transaction_type) }}</a>
                        @endif
                        @if($movement->reversal_of_id)
                            <br>Reverses movement #{{ $movement->reversal_of_id }}
                        @endif
                    </td>
                    <td class="numeric">
                        @if($movement->allocations->isNotEmpty())
                            <button type="button" class="inventory-batch-toggle"
                                @click="expandedMovements[{{ $movement->id }}] = !expandedMovements[{{ $movement->id }}]"
                                :aria-expanded="Boolean(expandedMovements[{{ $movement->id }}])"
                                aria-controls="movement-batches-{{ $movement->id }}"
                                aria-label="Batch details for {{ $movement->supply->supply_name }}: {{ $movement->allocations->count() }} {{ Str::plural('batch', $movement->allocations->count()) }}">
                                <span class="inventory-batch-arrow" aria-hidden="true">▸</span>
                                <span>{{ $type === 'expiry_verification' ? 'Dates verified' : $quantityLabel }}<small>{{ $movement->allocations->count() }} {{ Str::plural('batch', $movement->allocations->count()) }}</small></span>
                            </button>
                        @else
                            {{ $quantityLabel }}
                        @endif
                    </td>
                    <td class="numeric {{ $movement->quantity_after > $movement->quantity_before ? 'movement-up' : ($movement->quantity_after < $movement->quantity_before ? 'movement-down' : '') }}">
                        @if($movement->quantity_before !== null)<span class="movement-direction">{{ $movement->quantity_after > $movement->quantity_before ? '↑ Increased' : ($movement->quantity_after < $movement->quantity_before ? '↓ Decreased' : 'Unchanged') }}</span>@endif
                        {{ $movement->quantity_before === null ? 'Unavailable (legacy)' : number_format($movement->quantity_before, 2) . ' → ' . number_format($movement->quantity_after, 2).' '.$unit }}
                    </td>
                    <td>
                        {{ $movement->user->full_name }}
                    </td>
                    <td>{{ $movement->notes ?? '—' }}</td>
                </tr>
                @if($movement->allocations->isNotEmpty())
                    <tr class="inventory-batches-row" id="movement-batches-{{ $movement->id }}" x-show="expandedMovements[{{ $movement->id }}]" x-cloak>
                        <td colspan="7">
                            <div class="inventory-batch-details" aria-label="Batch details for {{ $movement->supply->supply_name }}">
                                @foreach($movement->allocations as $allocation)
                                    @php
                                        $amount = number_format(abs((float)$allocation->quantity), 2).' '.$unit;
                                        $remaining = number_format($allocation->quantity_after, 2).' '.$unit;
                                        $description = match($type) {
                                            'receipt' => 'New batch: '.$amount,
                                            'usage' => 'Used: '.$amount,
                                            'waste' => 'Discarded: '.$amount,
                                            'stocktake' => 'Counted: '.$remaining,
                                            'expiry_verification' => $allocation->quantity < 0 ? 'Transferred to dated batches: '.$amount : 'Verified stock: '.$amount,
                                            'reversal' => ($allocation->quantity > 0 ? 'Restored: ' : 'Removed by reversal: ').$amount,
                                            default => ($allocation->quantity > 0 ? 'Added: ' : 'Removed: ').$amount,
                                        };
                                    @endphp
                                    <div class="inventory-batch-detail">
                                        <a class="record-link" href="{{ route('inventory.history',['supply_id'=>$movement->supply_id,'stock_entry_id'=>$allocation->stock_entry_id]) }}">Batch #{{ $allocation->stock_entry_id }}</a>
                                        <span>{{ $description }}</span>
                                        @unless(in_array($type,['receipt','stocktake','expiry_verification']))<span>Remaining after: {{ $remaining }}</span>@endunless
                                        <span>{{ $allocation->entry->expiry_date ? 'Expires '.$allocation->entry->expiry_date->format('M j, Y') : ($movement->supply->category === 'packaging' ? 'Expiry not applicable' : 'Expiry unknown') }}</span>
                                        @if($allocation->reversal_of_id)<span>Reverses allocation #{{ $allocation->reversal_of_id }}</span>@endif
                                    </div>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="7" class="empty-state">No movements match these filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
