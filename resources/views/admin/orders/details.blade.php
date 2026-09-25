@extends('layouts.admin')
@section('title', 'Staff order details')
@section('content')
<div class="space-y-5">
    <div class="page-heading"><div><h1 class="font-bold text-cocoa-600">Customer and pickup</h1><p>Finish the staff order using the saved package selection.</p></div></div>
    @include('partials.catalog-order-details', ['staff' => true])
</div>
@endsection
