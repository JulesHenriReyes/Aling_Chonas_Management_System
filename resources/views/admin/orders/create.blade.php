@extends('layouts.admin')
@section('title', 'Create staff order')
@section('content')
<div class="space-y-6">
    <div class="page-heading">
        <div><h1 class="font-bold text-cocoa-600">Create staff order</h1><p class="mt-2">Use the same fixed package prices for orders taken in person, by phone or messaging.</p></div>
        <a href="{{ route('orders.index') }}" class="underline">Back to orders</a>
    </div>
    @include('partials.catalog-selection', ['staff' => true])
</div>
@endsection
