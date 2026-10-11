@if ($designChange['photos_expired_at'] ?? null)
    <p class="design-muted mb-4">Reference photos expired after the {{ config('order-retention.reference_photo_days') }}-day completed-order retention period. Order details and payment records remain available.</p>
@elseif ($designChange['retention_hold'] ?? false)
    <p class="design-muted mb-4">Reference photos are being kept for an ongoing review.</p>
@elseif ($order->status !== 'cancelled' && $order->images->isNotEmpty())
    <p class="design-muted mb-4">Reference photos are kept for {{ config('order-retention.reference_photo_days') }} days after order completion.</p>
@endif
