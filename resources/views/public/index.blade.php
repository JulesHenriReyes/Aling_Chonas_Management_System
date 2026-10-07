@extends('public.layout')
@section('title', 'Choose a cake package · Aling Chona')

@section('content')
<div class="storefront">
    <header class="store-heading">
        <div>
            <p class="eyebrow">Made for your celebration</p>
            <h1>Find your perfect package</h1>
            <p>Choose a cake, make it yours, and arrange your pickup.</p>
        </div>
        @if(count($draft['items'] ?? []))
            <a class="ui-button" href="#your-order">Your order · {{ count($draft['items']) }}</a>
        @endif
    </header>

    <x-ordering.progress :context="$context" :step="1" />

    <x-ordering.package-cards :products="$products" :context="$context" />

    <x-ordering.saved-order :draft-lines="$draftLines" :context="$context" />
</div>

<script>
try {
    const params = new URLSearchParams(location.search);
    const key = params.get('saved_line');
    if (key) {
        sessionStorage.removeItem('bakery-package-' + key);
        const orderBag = document.getElementById('your-order');
        if (orderBag) {
            orderBag.scrollIntoView({ behavior: 'smooth' });
        }
    }
} catch {}
</script>
@endsection
