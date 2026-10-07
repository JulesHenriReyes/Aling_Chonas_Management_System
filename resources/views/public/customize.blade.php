@extends('public.layout')
@section('title', 'Customize your package · Aling Chona')
@section('content')
<script src="{{ asset('js/catalog-order.js') }}?v={{ filemtime(public_path('js/catalog-order.js')) }}"></script>
<div class="storefront">
    <a class="back-link" href="{{ route($context['select_route']) }}">← All packages</a>
    <header class="store-heading"><div><h1>Make it yours</h1><p>Choose the layers, extras and design for {{ $product->product_name }}.</p></div></header>
    <x-ordering.progress :context="$context" :step="2" />
    <x-ordering.customization :context="$context" :products="$products" :product="$product" :line="$line" :editor="$editor" />
</div>
@endsection
