<div class="workspace-table table-scroll" role="region" aria-label="Stock movements" tabindex="0">
    <table>
        <thead>
            <tr>
                <th>Effective date</th>
                <th>Supply / unit</th>
                <th>Operation</th>
                <th class="numeric">Change</th>
                <th class="numeric">Before → after</th>
                <th>Recorded by / at</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @forelse($movements as $movement)
                <tr>
                    <td class="nowrap">{{ $movement->transaction_date->format('M d, Y') }}</td>
                    <td>
                        <a class="record-link" href="{{ route('supplies.show', $movement->supply) }}">{{ $movement->supply->supply_name }}</a><br>
                        {{ $movement->unit ?? $movement->supply->unit }}
                    </td>
                    <td>
                        @if($movement->operation)
                            <a class="record-link" href="{{ route('inventory.show', $movement->operation) }}">#{{ $movement->operation->id }} · {{ ucfirst($movement->operation->type) }}</a>
                        @else
                            <a class="record-link" href="{{ route('inventory.movement', $movement) }}">Legacy {{ str_replace('_', ' ', $movement->transaction_type) }}</a>
                        @endif
                        @if($movement->reversal_of_id)
                            <br>Reverses movement #{{ $movement->reversal_of_id }}
                        @endif
                    </td>
                    <td class="numeric">
                        {{ $movement->transaction_type === 'stock_out' ? '−' : ((float)$movement->quantity > 0 ? '+' : '') }}{{ number_format($movement->quantity, 2) }}
                    </td>
                    <td class="numeric">
                        {{ $movement->quantity_before === null ? 'Unavailable (legacy)' : number_format($movement->quantity_before, 2) . ' → ' . number_format($movement->quantity_after, 2) }}
                    </td>
                    <td>
                        {{ $movement->user->full_name }}<br>
                        <small>{{ $movement->created_at->format('M d, Y H:i:s') }}</small>
                    </td>
                    <td>{{ $movement->notes ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="empty-state">No movements match these filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
