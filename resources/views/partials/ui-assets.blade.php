@include('partials.ui-font-preloads')
<link rel="stylesheet" href="{{ asset('css/bakery-ui.css') }}?v={{ filemtime(public_path('css/bakery-ui.css')) }}">
<link rel="stylesheet" href="{{ asset('css/bakery-typography.css') }}?v={{ filemtime(public_path('css/bakery-typography.css')) }}">
<script defer src="{{ asset('js/bakery-ui.js') }}?v={{ filemtime(public_path('js/bakery-ui.js')) }}"></script>
<link rel="stylesheet" href="{{ asset('css/workspace-refinements.css') }}?v={{ filemtime(public_path('css/workspace-refinements.css')) }}">
