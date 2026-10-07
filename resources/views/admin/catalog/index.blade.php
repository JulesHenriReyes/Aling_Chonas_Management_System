@extends('layouts.admin')
@section('title', 'Catalog')
@section('content')
<div class="space-y-8">
    <div class="page-heading">
        <div><h1 class="font-bold text-cocoa-600">Cake packages and add-ons</h1><p class="mt-2">Layer options define the fixed price and included contents. Paid extras are listed separately.</p></div>
        @if (auth()->user()->isOwner())
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('payment-settings.edit') }}" class="ui-button">GCash settings</a>
                <a href="{{ route('products.create') }}" class="ui-button primary">Add cake package</a>
            </div>
        @endif
    </div>
    <section class="section-form space-y-5" aria-labelledby="packages-heading">
        <h2 id="packages-heading" class="font-semibold text-cocoa-600">Cake packages</h2>
        @forelse ($products as $product)
            <article class="border-t border-cocoa-100 pt-5 grid sm:grid-cols-[112px_1fr] gap-4">
                @if ($product->photo_path)<img src="{{ asset('storage/'.$product->photo_path) }}" alt="{{ $product->product_name }}" width="112" height="112" loading="lazy" class="w-28 h-28 object-cover rounded-lg">@else<div class="text-sm text-cocoa-400">No catalog photo yet</div>@endif
                <div class="space-y-2 min-w-0">
                    <div class="page-heading">
                        <h3 class="font-semibold text-cocoa-600">{{ $product->product_name }}</h3>
                        <x-status :value="$product->is_active ? 'active' : 'inactive'" :label="$product->is_active ? 'Available' : 'Unavailable'" />
                    </div>
                    @if ($product->description)<p>{{ $product->description }}</p>@endif
                    <div class="space-y-1.5 pt-1">
                        @forelse ($product->options as $option)
                            <div class="text-sm flex flex-wrap items-baseline gap-x-2 text-cocoa-700">
                                <span class="font-semibold text-cocoa-800">{{ $option->layers }} layer(s) · ₱{{ number_format($option->price, 2) }}</span>
                                @if (!$option->is_active)
                                    <x-status value="inactive" label="Unavailable" />
                                @endif
                                @if ($option->includedItems->isNotEmpty())
                                    <span class="text-cocoa-500">
                                        (Includes: {{ $option->includedItems->map(fn ($item) => $item->pivot->quantity . '× ' . $item->name)->join(', ') }})
                                    </span>
                                @elseif (filled($option->included_contents))
                                    <span class="text-cocoa-500">
                                        ({{ $option->included_contents }})
                                    </span>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-amber-800">Owner setup needed: add layer options, fixed prices and included contents before this package can be ordered.</p>
                        @endforelse
                    </div>
                    <p class="text-sm">{{ $product->order_details_count }} existing order line(s). Saved order prices are preserved.</p>
                    @if (auth()->user()->isOwner())
                        <div class="flex flex-wrap gap-3 items-center pt-1">
                            <a href="{{ route('products.edit', $product) }}" class="ui-button">Edit package and options</a>
                            <form action="{{ route('products.toggleStatus', $product) }}" method="POST" class="inline">
                                @csrf
                                @method('PATCH')
                                <button class="ui-button quiet">{{ $product->is_active ? 'Make unavailable' : 'Make available' }}</button>
                            </form>
                        </div>
                    @endif
                </div>
            </article>
        @empty<p>No cake packages yet. The Owner can add a package to begin.</p>@endforelse
    </section>
    <section class="section-form space-y-5" aria-labelledby="extras-heading">
        <div class="page-heading">
            <h2 id="extras-heading" class="font-semibold text-cocoa-600">Reusable items and paid extras</h2>
            @if (auth()->user()->isOwner())
                <a href="{{ route('add-ons.create') }}" class="ui-button">Add an add-on / item</a>
            @endif
        </div>
        @forelse ($addOns as $extra)
            <article class="border-t border-cocoa-100 pt-5 grid sm:grid-cols-[112px_1fr] gap-4">
                @if ($extra->photo_path)<img src="{{ asset('storage/'.$extra->photo_path) }}" alt="{{ $extra->name }}" width="112" height="112" loading="lazy" class="w-28 h-28 object-cover rounded-lg">@else<div class="text-sm text-cocoa-400">No catalog photo yet</div>@endif
                <div class="space-y-2">
                    <h3 class="font-semibold">{{ $extra->name }} · ₱{{ number_format($extra->price, 2) }} as a paid extra</h3>
                    <p>{{ $extra->description }}</p>
                    <div class="flex items-center gap-2 text-sm">
                        <x-status :value="$extra->is_active ? 'active' : 'inactive'" :label="$extra->is_active ? 'Paid extra enabled' : 'Paid extra disabled'" />
                        <span class="text-cocoa-500">· Offered with: {{ $extra->products->pluck('product_name')->join(', ') ?: 'No packages (inclusions only)' }}</span>
                    </div>
                    @if (auth()->user()->isOwner())
                        <div class="pt-1">
                            <a href="{{ route('add-ons.edit', $extra) }}" class="ui-button">Edit add-on</a>
                        </div>
                    @endif
                </div>
            </article>
        @empty<p>No paid add-ons configured. Included package contents are unaffected.</p>@endforelse
    </section>
</div>
@endsection
