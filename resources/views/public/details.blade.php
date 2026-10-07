@extends('public.layout')
@section('title', 'Contact and pickup · Aling Chona')
@section('content')
<nav class="store-progress mb-6" aria-label="Order progress">
    <a href="{{ route('public.order.index') }}">1. Choose package</a>
    <span>2. Customize</span>
    <span aria-current="step">3. Contact & pickup</span>
    <span>4. Staff review</span>
</nav>

<div class="public-intro mb-6">
    <h1 class="font-bold text-cocoa-700 text-2xl sm:text-3xl">Contact and pickup</h1>
</div>
@include('partials.catalog-order-details', ['staff' => false])
@endsection
