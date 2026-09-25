@extends('public.layout')
@section('title', 'Order cakes & cupcakes · Aling Chona')
@section('content')
<div class="public-intro">
    <h1 class="font-bold text-cocoa-600">Order cakes & cupcakes</h1>
    <p>Choose your celebration package, see what’s included, and add extras at fixed prices.</p>
</div>
@include('partials.catalog-selection', ['staff' => false])
@endsection
