@props(['value', 'label' => null])
@php
    $tone = match($value) {
        'pending', 'partially_paid', 'low_stock', 'awaiting_verification' => 'status-attention',
        'completed', 'fully_paid', 'ready_for_pickup', 'verified' => 'status-success',
        'cancelled', 'unpaid', 'rejected' => 'status-danger',
        default => '',
    };
@endphp
<span {{ $attributes->class(['status', $tone]) }}>{{ $label ?? ucfirst(str_replace('_', ' ', $value)) }}</span>
