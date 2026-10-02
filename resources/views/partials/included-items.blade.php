@if (!empty($includedItems) || filled($includedText))
    <div class="included-items text-sm space-y-1">
        <p class="font-semibold">Included per package · ₱0 extra</p>
        @if (!empty($includedItems))
            <ul class="space-y-1">
                @foreach ($includedItems as $included)
                    <li>
                        <span>{{ $included['name'] }} × {{ $included['quantity'] }} per package</span>
                        @if (($packageQuantity ?? 1) > 1)<span class="block text-xs">{{ $included['quantity'] * $packageQuantity }} across {{ $packageQuantity }} packages</span>@endif
                        @if (!empty($included['description']))<span class="block text-xs">{{ $included['description'] }}</span>@endif
                    </li>
                @endforeach
            </ul>
        @endif
        @if (filled($includedText))<p class="whitespace-pre-line">{{ $includedText }}</p>@endif
    </div>
@endif
