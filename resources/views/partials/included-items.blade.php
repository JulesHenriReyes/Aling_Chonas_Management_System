@if (!empty($includedItems) || filled($includedText))
    <div class="included-items text-sm space-y-1">
        <p class="font-semibold">Included per package · ₱0 extra</p>
        @if (!empty($includedItems))
            <ul class="space-y-1">
                @foreach ($includedItems as $included)
                    <li class="flex items-start gap-2 pt-0.5">
                        @if (!empty($included['photo_path']))
                            <img src="{{ asset('storage/' . $included['photo_path']) }}" alt="{{ $included['name'] }}" class="w-7 h-7 rounded object-cover border border-cocoa-100 shrink-0 bg-cream-50 mt-0.5">
                        @endif
                        <div class="min-w-0 flex-1">
                            <span>{{ $included['name'] }} × {{ $included['quantity'] }} per package</span>
                            @if (($packageQuantity ?? 1) > 1)<span class="block text-xs">{{ $included['quantity'] * $packageQuantity }} across {{ $packageQuantity }} packages</span>@endif
                            @if (!empty($included['description']))<span class="block text-xs text-cocoa-400">{{ $included['description'] }}</span>@endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
        @if (filled($includedText))<p class="whitespace-pre-line">{{ $includedText }}</p>@endif
    </div>
@endif
