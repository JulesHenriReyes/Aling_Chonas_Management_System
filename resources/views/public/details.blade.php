@extends('public.layout')
@section('title', 'Contact and pickup · Aling Chona')
@section('content')
<div class="public-intro">
    <h1 class="font-bold text-cocoa-600">Contact and pickup</h1>
    <p>Your packages are saved while you finish the order. Use Back to packages to make changes.</p>
</div>
@include('partials.catalog-order-details', ['staff' => false])
@endsection
