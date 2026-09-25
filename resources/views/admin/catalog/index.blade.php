@extends('layouts.admin')
@section('title', 'Catalog')
@section('content')
<div class="space-y-8">
    <div class="page-heading">
        <div><h1 class="font-bold text-cocoa-600">Cake packages and add-ons</h1><p class="mt-2">Layer options define the fixed price and included contents. Paid extras are listed separately.</p></div>
        @if (auth()->user()->isOwner())
            <div class="flex flex-wrap gap-3"><a href="{{ route('payment-settings.edit') }}" class="px-4 py-2 underline">GCash settings</a><a href="{{ route('products.create') }}" class="px-4 py-2 bg-cocoa-600 text-white rounded-lg">Add cake package</a></div>
        @endif
    </div>
    <section class="section-form space-y-5" aria-labelledby="packages-heading">
        <h2 id="packages-heading" class="font-semibold text-cocoa-600">Cake packages</h2>
        @forelse ($products as $product)
            <article class="border-t border-cocoa-100 pt-5 grid sm:grid-cols-[112px_1fr] gap-4">
                @if ($product->photo_path)<img src="{{ asset('storage/'.$product->photo_path) }}" alt="{{ $product->product_name }}" width="112" height="112" loading="lazy" class="w-28 h-28 object-cover rounded-lg">@else<div class="text-sm text-cocoa-400">No catalog photo yet</div>@endif
                <div class="space-y-2 min-w-0">
                    <div class="page-heading"><h3 class="font-semibold text-cocoa-600">{{ $product->product_name }}</h3><span>{{ $product->is_active ? 'Available' : 'Unavailable' }}</span></div>
                    @if ($product->description)<p>{{ $product->description }}</p>@endif
                    @forelse ($product->options as $option)
                        <p class="text-sm"><strong>{{ $option->layers }} layer(s) · ₱{{ number_format($option->price, 2) }}</strong>{{ !$option->is_active ? ' · Unavailable' : '' }}<br>Included: {{ $option->included_contents }}</p>
                    @empty
                        <p class="text-amber-800">Owner setup needed: add layer options, fixed prices and included contents before this package can be ordered.</p>
                    @endforelse
                    <p class="text-sm">{{ $product->order_details_count }} existing order line(s). Saved order prices are preserved.</p>
                    @if (auth()->user()->isOwner())
                        <div class="flex flex-wrap gap-3"><a href="{{ route('products.edit', $product) }}" class="px-3 py-2 underline">Edit package and options</a>
                            <form action="{{ route('products.toggleStatus', $product) }}" method="POST">@csrf @method('PATCH')<button class="px-3 py-2 underline">{{ $product->is_active ? 'Make unavailable' : 'Make available' }}</button></form></div>
                    @endif
                </div>
            </article>
        @empty<p>No cake packages yet. The Owner can add a package to begin.</p>@endforelse
    </section>
    <section class="section-form space-y-5" aria-labelledby="extras-heading">
        <div class="page-heading"><h2 id="extras-heading" class="font-semibold text-cocoa-600">Paid add-ons</h2>@if (auth()->user()->isOwner())<a href="{{ route('add-ons.create') }}" class="px-4 py-2 border border-cocoa-200 rounded-lg">Add an add-on</a>@endif</div>
        @forelse ($addOns as $extra)
            <article class="border-t border-cocoa-100 pt-5 grid sm:grid-cols-[112px_1fr] gap-4">
                @if ($extra->photo_path)<img src="{{ asset('storage/'.$extra->photo_path) }}" alt="{{ $extra->name }}" width="112" height="112" loading="lazy" class="w-28 h-28 object-cover rounded-lg">@else<div class="text-sm text-cocoa-400">No catalog photo yet</div>@endif
                <div class="space-y-2"><h3 class="font-semibold">{{ $extra->name }} · ₱{{ number_format($extra->price, 2) }}</h3><p>{{ $extra->description }}</p><p class="text-sm">{{ $extra->is_active ? 'Available' : 'Unavailable' }} · Applies to: {{ $extra->products->pluck('product_name')->join(', ') }}</p>
                    @if (auth()->user()->isOwner())<a href="{{ route('add-ons.edit', $extra) }}" class="inline-block underline py-2">Edit add-on</a>@endif</div>
            </article>
        @empty<p>No paid add-ons configured. Included package contents are unaffected.</p>@endforelse
    </section>
</div>
@endsection
