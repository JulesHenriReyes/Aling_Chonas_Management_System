@if (!empty($includedItems) || filled($includedText))
    <div class="included-items text-sm space-y-1">
        @if($showHeading ?? true)
            <p class="font-semibold inline-flex items-center gap-1 text-cocoa-700">Included per package (Free) <x-tooltip text="Complimentary items (e.g. candles, toppers, or boxes) bundled with this package at no extra charge." /></p>
        @endif
        @if (!empty($includedItems))
            <ul class="space-y-1">
                @foreach ($includedItems as $included)
                    @php
                        $includedPhoto = $included['photo_path'] ?? null;
                    @endphp
                    <li class="flex items-start gap-2 pt-0.5">
                        @if (!empty($includedPhoto))
                            <div x-data="{ expanded: false }" class="shrink-0 mt-0.5">
                                <button type="button" @click="expanded = true" class="block border border-cocoa-100 rounded overflow-hidden w-7 h-7 bg-cream-50 hover:opacity-80 transition focus:outline-none cursor-pointer" title="Click to view image" aria-label="View photo of {{ $included['name'] }}">
                                    <img src="{{ asset('storage/' . $includedPhoto) }}" alt="{{ $included['name'] }}" class="w-full h-full object-cover">
                                </button>
                                <template x-teleport="body">
                                    <div x-show="expanded" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center bg-black bg-opacity-80 p-4 backdrop-blur-sm" @keydown.escape.window="expanded = false" data-dialog role="dialog" aria-modal="true" aria-label="Included item photo preview">
                                        <div class="relative w-full h-full flex justify-center items-center" @click.outside="expanded = false">
                                            <button @click="expanded = false" class="absolute top-4 right-4 text-white hover:text-gray-300 focus:outline-none z-[110]" aria-label="Close image preview">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 drop-shadow-md" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                            </button>
                                            <img src="{{ asset('storage/' . $includedPhoto) }}" alt="{{ $included['name'] }}" class="max-w-full max-h-full object-contain rounded drop-shadow-2xl">
                                        </div>
                                    </div>
                                </template>
                            </div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <span>{{ $included['name'] }} × {{ $included['quantity'] }} per package</span>
                            @if (($packageQuantity ?? 1) > 1)<span class="block text-xs text-cocoa-400">{{ $included['quantity'] * $packageQuantity }} across {{ $packageQuantity }} packages</span>@endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
        @if (filled($includedText))<p class="whitespace-pre-line">{{ $includedText }}</p>@endif
    </div>
@endif
