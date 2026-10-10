@props(['records'])
<div class="workspace-footer">
    @if ($records->hasPages())
        {{ $records->links() }}
    @else
        <p>{{ $records->firstItem() ?? 0 }}–{{ $records->lastItem() ?? 0 }} of {{ number_format($records->total()) }} results</p>
    @endif
</div>
