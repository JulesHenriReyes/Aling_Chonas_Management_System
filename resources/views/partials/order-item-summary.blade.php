<div class="space-y-4">
    @foreach ($order->orderDetails as $detail)
        <div class="p-4 sm:p-5 rounded-xl bg-cream-50/50 border border-cocoa-100 space-y-3.5">
            {{-- Main Package Row with Thumbnail on Left --}}
            <div class="order-package-row flex items-start gap-3 sm:gap-4">
                @if ($detail->product?->photo_path)
                    <img src="{{ asset('storage/' . $detail->product->photo_path) }}"
                         alt="{{ $detail->product_name_snapshot ?? $detail->product->product_name }}"
                         class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl object-cover border border-cocoa-100/80 bg-white shrink-0 shadow-xs">
                @else
                    <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl bg-cocoa-50 border border-cocoa-100 flex items-center justify-center shrink-0 text-cocoa-400">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8.25v-1.5m0 1.5c-1.355 0-2.697.056-4.024.166C6.845 8.51 6 9.473 6 10.608v2.513m6-4.871c1.355 0 2.697.056 4.024.166C17.155 8.51 18 9.473 18 10.608v2.513M15 8.25v-1.5m-6 1.5v-1.5m12 9.75-1.5.75m-15-.75 1.5.75m0 0a3.75 3.75 0 0 0 7.5 0m7.5 0a3.75 3.75 0 0 1-7.5 0" /></svg>
                    </div>
                @endif

                <div class="order-package-copy min-w-0 flex-1 space-y-1">
                    <div class="flex justify-between items-start gap-2">
                        <h3 class="font-bold text-cocoa-700 text-sm sm:text-base leading-snug">
                            {{ $detail->product_name_snapshot ?? $detail->product->product_name }}
                            <span class="inline-block text-xs font-semibold text-cocoa-600 bg-white px-2 py-0.5 rounded-full border border-cocoa-100 ml-1">×{{ $detail->quantity }}</span>
                        </h3>
                        <span class="font-bold text-cocoa-700 whitespace-nowrap text-sm sm:text-base">₱{{ number_format($detail->quantity * $detail->unit_price, 2) }}</span>
                    </div>
                    <p class="text-xs text-cocoa-400">{{ $detail->layers ? $detail->layers . ' layer(s) · ' : '' }}₱{{ number_format($detail->unit_price, 2) }} each</p>
                </div>
            </div>

            @include('partials.included-items', ['includedItems' => $detail->included_items_snapshot ?? [], 'includedText' => $detail->included_contents_snapshot, 'packageQuantity' => $detail->quantity])

            {{-- Customizations: Theme & Design Request --}}
            @if ($detail->themes || $detail->special_request)
                <div class="pt-2 border-t border-cocoa-100/70 space-y-1.5 text-xs">
                    @if ($detail->themes)
                        <div class="flex items-center gap-1.5 text-cocoa-600">
                            <span class="font-semibold text-cocoa-700">Theme/Colors:</span>
                            <span>{{ $detail->themes }}</span>
                        </div>
                    @endif
                    @if ($detail->special_request)
                        <div class="text-cocoa-600">
                            <span class="font-semibold text-cocoa-700">Design request:</span>
                            <span>{{ $detail->special_request }}</span>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Reference Photos --}}
            @if ($detail->images && $detail->images->isNotEmpty())
                <div class="pt-2 border-t border-cocoa-100/70 space-y-1.5 text-xs">
                    <span class="font-semibold text-cocoa-700 block">Reference photos:</span>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($detail->images as $img)
                            <a href="{{ asset('storage/' . $img->file_path) }}" target="_blank" rel="noopener noreferrer" class="block border border-cocoa-100 rounded-lg overflow-hidden w-14 h-14 bg-white hover:opacity-80 transition shrink-0">
                                <img src="{{ asset('storage/' . $img->file_path) }}" alt="{{ $img->original_filename }}" class="w-full h-full object-cover">
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Paid Extras / Add-Ons with Image on their Left --}}
            @if ($detail->addOns->count() > 0)
                <div class="pt-3 border-t border-cocoa-100/70 space-y-2">
                    <span class="text-xs font-semibold text-cocoa-700 block">Paid extras · quantities for this whole order line</span>
                    <div class="space-y-2">
                        @foreach ($detail->addOns as $extra)
                            <div class="flex items-center justify-between gap-3 bg-white p-2.5 rounded-xl border border-cocoa-100/80">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    @if ($extra->addOn?->photo_path)
                                        <img src="{{ asset('storage/' . $extra->addOn->photo_path) }}"
                                             alt="{{ $extra->name_snapshot }}"
                                             class="w-11 h-11 sm:w-12 sm:h-12 rounded-lg object-cover border border-cocoa-100 shrink-0 bg-cream-50">
                                    @else
                                        <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-lg bg-cocoa-50 border border-cocoa-100 flex items-center justify-center shrink-0 text-cocoa-300">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="font-semibold text-cocoa-700 text-xs sm:text-sm truncate">
                                            {{ $extra->name_snapshot }}
                                            <span class="font-normal text-cocoa-400 ml-1">×{{ $extra->quantity }}</span>
                                        </p>
                                        @if ($extra->description_snapshot)
                                            <p class="text-[11px] text-cocoa-400 truncate max-w-xs sm:max-w-sm">{{ $extra->description_snapshot }}</p>
                                        @endif
                                    </div>
                                </div>
                                <span class="font-semibold text-cocoa-700 text-xs sm:text-sm whitespace-nowrap">₱{{ number_format($extra->subtotal, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endforeach
</div>
