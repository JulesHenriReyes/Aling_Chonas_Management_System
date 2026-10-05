@props([
    'type' => 'block',
    'lines' => 3,
])

@if ($type === 'metric')
    <div {{ $attributes->merge(['class' => 'skeleton-card skeleton-metric']) }}>
        <div class="skeleton skeleton-pulse w-24 h-3 mb-2"></div>
        <div class="skeleton skeleton-pulse w-16 h-7 mb-2"></div>
        <div class="skeleton skeleton-pulse w-36 h-3"></div>
    </div>
@elseif ($type === 'card')
    <div {{ $attributes->merge(['class' => 'skeleton-card']) }}>
        <div class="skeleton skeleton-pulse skeleton-title mb-4"></div>
        <div class="space-y-2">
            @for ($i = 0; $i < $lines; $i++)
                <div class="skeleton skeleton-pulse skeleton-text" style="width: {{ [85, 70, 90, 60][$i % 4] }}%;"></div>
            @endfor
        </div>
    </div>
@elseif ($type === 'text')
    <div {{ $attributes->merge(['class' => 'skeleton skeleton-pulse skeleton-text']) }}></div>
@elseif ($type === 'title')
    <div {{ $attributes->merge(['class' => 'skeleton skeleton-pulse skeleton-title']) }}></div>
@else
    <div {{ $attributes->merge(['class' => 'skeleton skeleton-pulse']) }}></div>
@endif
