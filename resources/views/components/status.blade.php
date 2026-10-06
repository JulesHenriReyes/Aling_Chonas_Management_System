@props(['value', 'label' => null, 'tooltip' => null, 'align' => 'center'])
@php
    $tone = match($value) {
        'pending', 'partially_paid', 'low_stock', 'low', 'awaiting_verification' => 'status-attention',
        'completed', 'fully_paid', 'ready_for_pickup', 'verified', 'in_stock', 'healthy', 'active' => 'status-success',
        'cancelled', 'unpaid', 'rejected', 'out_of_stock', 'out', 'inactive' => 'status-danger',
        default => '',
    };

    $statusDescriptions = [
        // Order workflow labels & statuses
        'pending' => 'Order is awaiting initial review and confirmation by bakery staff before payment is requested.',
        'awaiting staff confirmation' => 'Order is awaiting initial review and confirmation by bakery staff before payment is requested.',
        'pending review' => 'Order is awaiting initial review and confirmation by bakery staff before payment is requested.',
        'staff review — reported payment needs checking' => 'Customer reported a prior payment that must be audited by staff before approval.',
        'staff review - reported payment needs checking' => 'Customer reported a prior payment that must be audited by staff before approval.',
        'request declined' => 'Order request was declined by staff; no production scheduled.',
        'confirmed' => 'Order was accepted by staff; 50% deposit required to secure the baking schedule.',
        'confirmed — awaiting deposit' => 'Order was accepted by staff; awaiting 50% deposit from customer to lock schedule.',
        'confirmed - awaiting deposit' => 'Order was accepted by staff; awaiting 50% deposit from customer to lock schedule.',
        'receipt awaiting verification' => 'Customer uploaded payment proof; staff verification required to confirm deposit.',
        'deposit verified — booking secured' => 'Down payment received and verified; baking schedule slot is officially reserved.',
        'deposit verified - booking secured' => 'Down payment received and verified; baking schedule slot is officially reserved.',
        'previously confirmed' => 'Order was previously confirmed in legacy records.',
        'preparing' => 'In production: kitchen is actively baking and decorating this order.',
        'ready_for_pickup' => 'Order has been baked and assembled; ready at the store for customer collection.',
        'ready for pickup' => 'Order has been baked and assembled; ready at the store for customer collection.',
        'completed' => 'Order has been picked up and all balances are settled in full.',
        'cancelled' => 'Order was cancelled; no baking or pickup scheduled.',
        // Payment statuses
        'unpaid' => 'No payment has been recorded yet for this order.',
        'partially_paid' => '50% down payment received; remaining balance due upon pickup.',
        'partially paid' => '50% down payment received; remaining balance due upon pickup.',
        'fully_paid' => 'Total order price has been paid in full (100% settled).',
        'fully paid' => 'Total order price has been paid in full (100% settled).',
        // Payment proof verification statuses
        'awaiting_verification' => 'Payment receipt uploaded by customer is awaiting staff verification.',
        'verified' => 'Payment receipt has been verified and applied to the order balance.',
        'rejected' => 'Payment receipt was declined; customer must re-upload valid proof.',
        // Inventory stock statuses
        'in_stock' => 'Current inventory level is healthy and above minimum reorder threshold.',
        'in stock' => 'Current inventory level is healthy and above minimum reorder threshold.',
        'healthy' => 'Current inventory level is healthy and above minimum reorder threshold.',
        'low_stock' => 'Current stock is at or below the reorder point; replenishment needed soon.',
        'low stock' => 'Current stock is at or below the reorder point; replenishment needed soon.',
        'low' => 'Current stock is at or below the reorder point; replenishment needed soon.',
        'out_of_stock' => 'Zero inventory remaining on hand; replenishment urgently required.',
        'out of stock' => 'Zero inventory remaining on hand; replenishment urgently required.',
        'out' => 'Zero inventory remaining on hand; replenishment urgently required.',
        'inactive' => 'Supply item is archived / inactive and hidden from active inventory operations.',
        'active' => 'Supply item is active and available for use in inventory operations.',
    ];

    $labelRaw = $label ? strtolower(trim($label)) : null;
    $labelNorm = $labelRaw ? str_replace(['—', '–'], '-', $labelRaw) : null;
    $valRaw = $value ? strtolower(trim((string)$value)) : null;
    $valNorm = $valRaw ? str_replace(['—', '–'], '-', $valRaw) : null;

    $resolvedTooltip = $tooltip !== false ? ($tooltip ?? (
        $statusDescriptions[$labelRaw] ??
        $statusDescriptions[$labelNorm] ??
        $statusDescriptions[$valRaw] ??
        $statusDescriptions[$valNorm] ??
        null
    )) : null;
@endphp
@if($resolvedTooltip)
    <x-tooltip :text="$resolvedTooltip" :align="$align">
        <span {{ $attributes->class(['status', $tone, 'cursor-help']) }}>{{ $label ?? ucfirst(str_replace('_', ' ', $value)) }}</span>
    </x-tooltip>
@else
    <span {{ $attributes->class(['status', $tone]) }}>{{ $label ?? ucfirst(str_replace('_', ' ', $value)) }}</span>
@endif
