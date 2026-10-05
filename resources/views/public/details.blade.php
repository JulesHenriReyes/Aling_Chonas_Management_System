@extends('public.layout')
@section('title', 'Contact and pickup · Aling Chona')
@section('content')
<nav aria-label="Progress" class="mb-5">
    <div class="flex items-center text-xs tracking-wide">
        <a href="{{ route('public.order.index') }}" class="text-cocoa-700 hover:text-cocoa-900 transition">Packages</a>
        <span class="text-cocoa-300 mx-2">/</span>
        <span class="text-cocoa-700 font-semibold" aria-current="step">Contact & pickup</span>
        <span class="text-cocoa-300 mx-2">/</span>
        <span class="text-cocoa-400">Staff review</span>
    </div>
</nav>

<div class="public-intro mb-6">
    <h1 class="font-bold text-cocoa-700 text-2xl sm:text-3xl">Contact and pickup</h1>
</div>
@include('partials.catalog-order-details', ['staff' => false])
@endsection
