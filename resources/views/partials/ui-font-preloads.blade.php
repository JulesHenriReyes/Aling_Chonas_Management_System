{{-- Match the font-face URLs exactly so navigation reuses the preloaded local fonts. --}}
@foreach (['calistoga-400', 'dm-sans-400', 'dm-sans-500', 'dm-sans-600', 'dm-sans-700'] as $font)
<link rel="preload" href="{{ asset('fonts/'.$font.'.ttf') }}" as="font" type="font/ttf" crossorigin>
@endforeach
