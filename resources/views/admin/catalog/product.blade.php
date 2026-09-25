@extends('layouts.admin')
@section('title', $product->exists ? 'Edit cake package' : 'Add cake package')
@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="page-heading"><h1 class="font-bold text-cocoa-600">{{ $product->exists ? 'Edit cake package' : 'Add cake package' }}</h1><a href="{{ route('products.index') }}" class="underline">Back to catalog</a></div>
    <form action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}" method="POST" enctype="multipart/form-data" class="section-form space-y-4">
        @csrf @if ($product->exists) @method('PATCH') @endif
        <div><label for="product-name">Package name</label><input id="product-name" name="product_name" required maxlength="255" value="{{ old('product_name', $product->product_name) }}" class="w-full"></div>
        <div><label for="description">Description</label><textarea id="description" name="description" rows="3" maxlength="2000" class="w-full">{{ old('description', $product->description) }}</textarea></div>
        @if ($product->photo_path)<img src="{{ asset('storage/'.$product->photo_path) }}" alt="Current package photo" width="160" height="160" class="w-40 h-40 object-cover rounded-lg">@endif
        <div><label for="photo">Public catalog photo</label><input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="photo-help"><p id="photo-help" class="text-sm mt-1">JPG, PNG or WebP, up to 5 MB. Leave empty to keep the existing photo. Customer reference images stay with their orders.</p></div>
        <input type="hidden" name="is_active" value="0">
        <label class="flex items-center gap-3"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active))>Package available</label>
        <button class="px-5 py-3 bg-cocoa-600 text-white rounded-lg hover:bg-cocoa-700">Save package</button>
    </form>
    @if ($product->exists)
        <section class="section-form space-y-5" aria-labelledby="layer-options"><h2 id="layer-options" class="font-semibold text-cocoa-600">Layer options and inclusions</h2>
            <p class="text-sm">List all contents included in each option. Prices use ₱0.02 increments so every order supports an exact 50% deposit. Existing orders keep their saved prices and inclusions.</p>
            @foreach ($product->options as $option)
                @include('admin.catalog.option-form', ['option' => $option])
            @endforeach
            @include('admin.catalog.option-form', ['option' => new \App\Models\PackageOption(['is_active' => true])])
        </section>
    @endif
</div>
@endsection
