@props(['records'])
<div class="workspace-footer">
    <p>{{ $records->firstItem() ?? 0 }}–{{ $records->lastItem() ?? 0 }} of {{ number_format($records->total()) }} results</p>
    {{ $records->links() }}
</div>
