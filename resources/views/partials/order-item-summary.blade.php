<div class="divide-y divide-cocoa-100">
    @foreach ($order->orderDetails as $detail)
        <section class="py-4 space-y-2">
            <div class="flex justify-between gap-4">
                <h3 class="font-semibold text-cocoa-600">{{ $detail->product_name_snapshot ?? $detail->product->product_name }} × {{ $detail->quantity }}</h3>
                <span class="font-semibold whitespace-nowrap">₱{{ number_format($detail->quantity * $detail->unit_price, 2) }}</span>
            </div>
            <p class="text-sm">{{ $detail->layers ? $detail->layers . ' layer(s) · ' : '' }}₱{{ number_format($detail->unit_price, 2) }} per package</p>
            @if ($detail->included_contents_snapshot)
                <p class="text-sm"><strong>Included:</strong> {{ $detail->included_contents_snapshot }}</p>
            @endif
            @foreach ($detail->addOns as $extra)
                <div class="flex justify-between gap-4 text-sm">
                    <div><strong>Paid extra:</strong> {{ $extra->name_snapshot }} × {{ $extra->quantity }}<br>
                        {{ $extra->description_snapshot }} · ₱{{ number_format($extra->unit_price, 2) }} each</div>
                    <span class="whitespace-nowrap">₱{{ number_format($extra->subtotal, 2) }}</span>
                </div>
            @endforeach
            @if ($detail->themes)<p class="text-sm"><strong>Theme:</strong> {{ $detail->themes }}</p>@endif
            @if ($detail->special_request)<p class="text-sm"><strong>Design request:</strong> {{ $detail->special_request }}</p>@endif
        </section>
    @endforeach
</div>
