@extends('layouts.admin')
@section('title', 'Catalog')
@section('content')
<div class="space-y-8">
    <header class="workspace-heading">
        <div>
            <h1 class="text-xl md:text-2xl font-bold text-cocoa-900 tracking-tight">Cake packages &amp; add-ons</h1>
            <p class="text-xs md:text-sm text-cocoa-500 mt-1">Layer options define the fixed price and included contents. Paid extras are listed separately.</p>
        </div>
        @if (auth()->user()->isOwner())
            <div class="workspace-actions">
                <a href="{{ route('payment-settings.edit') }}" class="ui-button text-xs py-2 px-3.5">
                    GCash settings
                </a>
                <a href="{{ route('products.create') }}" class="ui-button primary text-xs py-2 px-3.5">
                    <x-icon name="plus" class="w-3.5 h-3.5" />
                    <span>Add cake package</span>
                </a>
            </div>
        @endif
    </header>

    {{-- Cake Packages Section --}}
    <section class="space-y-4" aria-labelledby="packages-heading">
        <div class="flex items-center justify-between pb-1 border-b border-cocoa-100/80">
            <div class="flex items-center gap-2.5">
                <h2 id="packages-heading" class="text-base md:text-lg font-bold text-cocoa-900">Cake packages</h2>
                <span class="catalog-section-badge">{{ $products->count() }}</span>
            </div>
            <p class="text-xs text-cocoa-400 hidden sm:block">Fixed-size packages with customizable layers and free inclusions</p>
        </div>

        <div class="grid gap-4">
            @forelse ($products as $product)
                <article class="catalog-package-card">
                    <div class="grid sm:grid-cols-[140px_1fr] gap-5 items-start">
                        {{-- Photo Thumbnail --}}
                        <div class="w-full sm:w-[140px] h-36 rounded-xl overflow-hidden bg-cream-100 border border-cocoa-100/80 shrink-0 relative flex items-center justify-center shadow-2xs">
                            @if ($product->photo_path)
                                <img src="{{ asset('storage/'.$product->photo_path) }}" alt="{{ $product->product_name }}" loading="lazy" class="w-full h-full object-cover">
                            @else
                                <div class="flex flex-col items-center justify-center text-cocoa-300 p-2 text-center">
                                    <x-icon name="cake" class="w-8 h-8 opacity-60 mb-1" />
                                    <span class="text-[11px] font-medium text-cocoa-400">No catalog photo yet</span>
                                </div>
                            @endif
                        </div>

                        {{-- Details --}}
                        <div class="space-y-3 min-w-0">
                            {{-- Title & Status --}}
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="text-base font-bold text-cocoa-900 leading-snug">
                                        {{ $product->product_name }}
                                    </h3>
                                    @if ($product->description)
                                        <p class="text-xs text-cocoa-500 mt-1 leading-relaxed max-w-3xl">
                                            {{ $product->description }}
                                        </p>
                                    @endif
                                </div>
                                <div class="shrink-0">
                                    <x-status :value="$product->is_active ? 'active' : 'inactive'" :label="$product->is_active ? 'Available' : 'Unavailable'" />
                                </div>
                            </div>

                            {{-- Layer Pricing Options (Compact Tier Chips) --}}
                            <div class="space-y-1.5 pt-0.5">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-cocoa-400 block">Available Layer Options &amp; Fixed Prices</span>
                                <div class="flex flex-wrap gap-2">
                                    @forelse ($product->options as $option)
                                        <div class="catalog-tier-chip {{ $option->is_active ? '' : 'opacity-60 bg-stone-50 border-stone-200' }}">
                                            <div class="flex items-baseline gap-1.5">
                                                <span class="text-xs font-semibold text-cocoa-700">{{ $option->layers }} {{ $option->layers == 1 ? 'layer' : 'layers' }}</span>
                                                <strong class="text-sm font-extrabold text-cocoa-900 tabular-nums">₱{{ number_format($option->price, 2) }}</strong>
                                            </div>
                                            @if (!$option->is_active)
                                                <span class="text-[10px] uppercase font-bold text-stone-500 px-1.5 py-0.2 bg-stone-200/80 rounded">Unavailable</span>
                                            @endif
                                            @if ($option->includedItems->isNotEmpty())
                                                <span class="text-[11px] text-cocoa-600 border-l border-cocoa-200/60 pl-2 flex items-center gap-1">
                                                    <span class="text-cocoa-400">Includes:</span>
                                                    <strong class="font-semibold text-cocoa-700">{{ $option->includedItems->map(fn ($item) => $item->pivot->quantity . '× ' . $item->name)->join(', ') }}</strong>
                                                </span>
                                            @elseif (filled($option->included_contents))
                                                <span class="text-[11px] text-cocoa-600 border-l border-cocoa-200/60 pl-2 flex items-center gap-1">
                                                    <span class="text-cocoa-400">Includes:</span>
                                                    <strong class="font-semibold text-cocoa-700">{{ $option->included_contents }}</strong>
                                                </span>
                                            @endif
                                        </div>
                                    @empty
                                        <p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                                            Owner setup needed: add layer options, fixed prices and included contents before this package can be ordered.
                                        </p>
                                    @endforelse
                                </div>
                            </div>

                            {{-- Footer / Actions --}}
                            <div class="flex flex-wrap items-center justify-between gap-3 pt-2.5 border-t border-cocoa-100/70">
                                <p class="text-xs text-cocoa-400">
                                    <span class="font-semibold text-cocoa-600">{{ $product->order_details_count }}</span> existing order line(s) · saved order prices preserved
                                </p>
                                @if (auth()->user()->isOwner())
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('products.edit', $product) }}" class="ui-button text-xs py-1.5 px-3">
                                            Edit package and options
                                        </a>
                                        <form action="{{ route('products.toggleStatus', $product) }}" method="POST" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="catalog-toggle-btn {{ $product->is_active ? 'catalog-toggle-disable' : 'catalog-toggle-enable' }}">
                                                {{ $product->is_active ? 'Make unavailable' : 'Make available' }}
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="bg-white rounded-xl border border-cocoa-100 p-8 text-center text-sm text-cocoa-400">
                    No cake packages yet. The Owner can add a package to begin.
                </div>
            @endforelse
        </div>
    </section>

    {{-- Reusable Items and Paid Extras Section --}}
    <section class="space-y-4" aria-labelledby="extras-heading">
        <div class="flex items-center justify-between pb-1 border-b border-cocoa-100/80">
            <div class="flex items-center gap-2.5">
                <h2 id="extras-heading" class="text-base md:text-lg font-bold text-cocoa-900">Reusable items and paid extras</h2>
                <span class="catalog-section-badge">{{ $addOns->count() }}</span>
            </div>
            @if (auth()->user()->isOwner())
                <a href="{{ route('add-ons.create') }}" class="ui-button text-xs py-1.5 px-3">
                    <x-icon name="plus" class="w-3.5 h-3.5" />
                    <span>Add an add-on / item</span>
                </a>
            @endif
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            @forelse ($addOns as $extra)
                <article class="catalog-addon-card">
                    <div class="space-y-2.5">
                        <div class="flex items-start gap-3.5">
                            <div class="w-16 h-16 rounded-lg overflow-hidden bg-cream-100 border border-cocoa-100/80 shrink-0 relative flex items-center justify-center shadow-2xs">
                                @if ($extra->photo_path)
                                    <img src="{{ asset('storage/'.$extra->photo_path) }}" alt="{{ $extra->name }}" loading="lazy" class="w-full h-full object-cover">
                                @else
                                    <div class="text-[10px] text-cocoa-400 text-center p-1 font-medium">No photo</div>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="text-sm font-bold text-cocoa-900 leading-snug">
                                        {{ $extra->name }}
                                    </h3>
                                    <span class="text-xs font-extrabold text-cocoa-800 tabular-nums shrink-0">
                                        ₱{{ number_format($extra->price, 2) }}
                                    </span>
                                </div>
                                <span class="text-[10px] text-cocoa-400 block mt-0.5">Paid extra price</span>
                                <div class="mt-1.5">
                                    <x-status :value="$extra->is_active ? 'active' : 'inactive'" :label="$extra->is_active ? 'Paid extra enabled' : 'Paid extra disabled'" />
                                </div>
                            </div>
                        </div>

                        @if ($extra->description)
                            <p class="text-xs text-cocoa-500 line-clamp-2 leading-relaxed">
                                {{ $extra->description }}
                            </p>
                        @endif

                        <div class="association-tags pt-1">
                            @if($extra->all_packages)
                                <span class="association-tag all-scope">★ All current &amp; future packages</span>
                            @elseif($products->isNotEmpty() && $extra->products->count() === $products->count())
                                <span class="association-tag">All current packages</span>
                            @else
                                @forelse($extra->products as $offered)
                                    <span class="association-tag">{{ $offered->product_name }}</span>
                                @empty
                                    <span class="association-tag">Inclusions only</span>
                                @endforelse
                            @endif
                        </div>
                    </div>

                    @if (auth()->user()->isOwner())
                        <div class="pt-2 border-t border-cocoa-100/60 flex items-center justify-end">
                            <a href="{{ route('add-ons.edit', $extra) }}" class="ui-button text-xs py-1.5 px-3">
                                Edit add-on
                            </a>
                        </div>
                    @endif
                </article>
            @empty
                <div class="col-span-2 bg-white rounded-xl border border-cocoa-100 p-8 text-center text-sm text-cocoa-400">
                    No paid add-ons configured. Included package contents are unaffected.
                </div>
            @endforelse
        </div>
    </section>
</div>
@endsection
