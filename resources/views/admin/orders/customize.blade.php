@extends('layouts.admin')
@section('title', 'Customize staff package')
@section('content')
<script src="{{ asset('js/catalog-order.js') }}?v={{ filemtime(public_path('js/catalog-order.js')) }}"></script>
<div class="staff-ordering storefront">
    <a class="back-link" href="{{ route('orders.create') }}">← Back to packages</a>
    <header class="store-heading"><div><h1>Customize package</h1><p>Enter the customer's layers, extras and design for {{ $product->product_name }}.</p></div></header>
    <x-ordering.progress :context="$context" :step="2" />
    <x-ordering.customization :context="$context" :products="$products" :product="$product" :line="$line" :editor="$editor" />
</div>
@endsection
