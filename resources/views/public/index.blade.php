@extends('public.layout')
@section('title', 'Choose a cake package · Aling Chona')
@section('content')
<div class="storefront"><header class="store-heading"><div><p class="eyebrow">Made for your celebration</p><h1>Find your perfect package</h1><p>Choose a cake, make it yours, and arrange your pickup.</p></div>@if(count($draft['items'] ?? []))<a class="ui-button" href="#your-order">Your order · {{ count($draft['items']) }}</a>@endif</header>
<nav class="store-progress" aria-label="Order progress"><span aria-current="step">1. Choose package</span><span>2. Customize</span><span>3. Contact & pickup</span><span>4. Staff review</span></nav>
<section class="store-catalog" aria-label="Cake packages">
@forelse($products as $product)<article class="store-package">
@if($product->photo_path)<img class="store-package-photo" src="{{ asset('storage/'.$product->photo_path) }}" alt="{{ $product->product_name }}" width="400" height="400" loading="{{ $loop->index < 2 ? 'eager' : 'lazy' }}">@else<div class="store-package-photo photo-placeholder"><x-icon name="cake" /><span>{{ $product->product_name }}</span></div>@endif
<div class="store-package-body"><h2>{{ $product->product_name }}</h2>@if($product->description)<p>{{ $product->description }}</p>@endif<strong class="store-package-price">From ₱{{ number_format($product->options->min('price'),2) }}</strong><a class="ui-button primary" href="{{ route('public.package.customize',['product'=>$product,'line'=>(string) Str::uuid()]) }}" aria-label="Select {{ $product->product_name }} package">Select package</a></div></article>
@empty<p class="empty-state">Packages are being updated. Please check back or contact the bakery.</p>@endforelse
</section>
@if(count($draftLines))<section class="order-bag" id="your-order" aria-labelledby="bag-title"><div class="workspace-heading"><h2 id="bag-title">Your order</h2><a class="ui-button quiet" href="#main-content">Add another package</a></div>
@foreach($draftLines as $entry) @php($item=$entry['item'])<article class="bag-line"><div class="bag-line-content" style="display:flex; gap:0.875rem; align-items:flex-start;">
@if($entry['product']?->photo_path)
<img src="{{ asset('storage/'.$entry['product']->photo_path) }}" alt="{{ $entry['product']->product_name }}" style="width:3.5rem; height:3.5rem; border-radius:0.5rem; object-fit:cover; border:1px solid var(--bakery-line); flex-shrink:0;">
@else
<div style="width:3.5rem; height:3.5rem; border-radius:0.5rem; background:var(--bakery-bg); border:1px solid var(--bakery-line); display:flex; align-items:center; justify-content:center; flex-shrink:0;"><x-icon name="cake" style="width:1.75rem; height:1.75rem; color:var(--bakery-muted);" /></div>
@endif
<div><h3>{{ $entry['product']?->product_name ?? 'Unavailable package' }} × {{ $item['quantity'] }}</h3>@if($entry['quote'])<p>{{ $entry['quote']['lines'][0]['layers'] }} layer(s) · ₱{{ number_format($entry['quote']['total'],2) }}</p>@endif @if($item['themes'] ?? null)<p>{{ $item['themes'] }}</p>@endif
@foreach($entry['quote']['lines'][0]['add_ons'] ?? [] as $extra)<p class="form-hint">{{ $extra['name_snapshot'] }} × {{ $extra['quantity'] }} · ₱{{ number_format($extra['unit_price']*$extra['quantity'],2) }}</p>@endforeach
@foreach($item['staged_images'] ?? [] as $image)<p class="form-hint">Reference: {{ $image['original_filename'] }}</p>@endforeach @if($entry['error'])<p class="field-error">{{ $entry['error'] }}</p>@endif</div></div><div class="workspace-actions">@if($entry['product']?->is_active)<a class="ui-button" href="{{ route('public.package.customize',['product'=>$item['product_id'],'line'=>$item['draft_key']]) }}">Edit</a>@endif<form method="POST" action="{{ route('public.package.remove',$item['draft_key']) }}">@csrf<button class="ui-button quiet">Remove</button></form></div></article>@endforeach
<div class="bag-total"><p>Order total <strong>₱{{ number_format(collect($draftLines)->sum(fn($line) => $line['quote']['total'] ?? 0),2) }}</strong></p>@unless(collect($draftLines)->contains(fn($line)=>$line['error']))<a class="ui-button primary" href="{{ route('public.order.details') }}">Continue to contact and pickup</a>@endunless</div></section>@endif
</div>
<script>try { const key = new URLSearchParams(location.search).get('saved_line'); if (key) sessionStorage.removeItem('bakery-package-'+key); } catch {}</script>
@endsection
