@extends('layouts.admin')
@section('title', 'Staff order details')
@section('content')
<div class="staff-ordering storefront">
    <header class="page-heading"><div><h1 class="font-bold text-cocoa-600">Customer and pickup</h1><p>Check the saved packages, customer and pickup schedule before creating the order.</p></div></header>
    <x-ordering.progress :context="$context" :step="3" />
    @include('partials.catalog-order-details', ['staff' => true, 'context' => $context])
</div>
@endsection
