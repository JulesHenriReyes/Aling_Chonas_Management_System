@extends('layouts.admin')
@section('title', 'Create staff order')
@section('content')
<div class="staff-ordering storefront" data-staff-draft-prefix="{{ $context['browser_prefix'] }}">
    <header class="page-heading"><div><h1 class="font-bold text-cocoa-600">Create staff order</h1><p class="mt-2">Choose packages for an order taken in person, by phone or messaging.</p></div><a class="ui-button quiet" href="{{ route('orders.index') }}">Back to orders</a></header>
    <x-ordering.progress :context="$context" :step="1" />
    <x-ordering.package-cards :products="$products" :context="$context" :package-links="$packageLinks" />
    <x-ordering.saved-order :draft-lines="$draftLines" :context="$context" />
</div>
<script>
try {
    const prefix = @js($context['browser_prefix']);
    const saved = new URLSearchParams(location.search).get('saved_line');
    const removed = @js(session('removed_staff_line'));
    if (saved || removed) sessionStorage.removeItem(prefix + '-' + (saved || removed));
    if (saved) document.getElementById('your-order')?.scrollIntoView({block:'start'});
} catch {}
</script>
@endsection
