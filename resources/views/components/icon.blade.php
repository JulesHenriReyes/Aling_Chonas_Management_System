@props(['name' => 'clipboard', 'path' => null])
@php
    $paths = [
        'clipboard' => 'M9 5.25H6A2.25 2.25 0 0 0 3.75 7.5v12A2.25 2.25 0 0 0 6 21.75h12a2.25 2.25 0 0 0 2.25-2.25v-12A2.25 2.25 0 0 0 18 5.25h-3M9 5.25v-1.5A1.5 1.5 0 0 1 10.5 2.25h3A1.5 1.5 0 0 1 15 3.75v1.5H9Zm-.75 6h7.5m-7.5 4.5h7.5',
        'warning' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
        'check' => 'm4.5 12.75 6 6 9-13.5',
        'plus' => 'M12 4.5v15m7.5-7.5h-15',
        'arrow-left' => 'M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18',
        'arrow-right' => 'M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3',
        'external' => 'M7.5 3.75H3.75v16.5h16.5V16.5M12 3.75h8.25V12m0-8.25L9 15',
        'close' => 'm6 6 12 12M6 18 18 6',
    ];
@endphp
<svg {{ $attributes->class(['ui-icon']) }} aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path ?? $paths[$name] ?? $paths['clipboard'] }}" /></svg>
