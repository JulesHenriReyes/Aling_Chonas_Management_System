@extends('layouts.admin')
@section('title', $product->exists ? 'Edit cake package' : 'Add cake package')
@section('content')
<script defer src="{{ asset('js/catalog-sections.js') }}?v={{ filemtime(public_path('js/catalog-sections.js')) }}"></script>
<script src="{{ asset('js/package-inclusions.js') }}?v={{ filemtime(public_path('js/package-inclusions.js')) }}"></script>
<div class="max-w-3xl mx-auto space-y-6">
    <div class="page-heading"><h1 class="font-bold text-cocoa-600">{{ $product->exists ? 'Edit cake package' : 'Add cake package' }}</h1><a href="{{ route('products.index') }}" class="ui-button">Back to catalog</a></div>
    <form action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}" method="POST" enctype="multipart/form-data" class="section-form space-y-4 catalog-section" @if($product->exists) data-catalog-save @endif>
        <strong class="section-title">Package details</strong><span class="section-note">Save changes in this section independently. Other layer edits stay on this page.</span>
        @csrf @if ($product->exists) @method('PATCH') @endif
        <div><label for="product-name">Package name</label><input id="product-name" name="product_name" required maxlength="255" value="{{ old('product_name', $product->product_name) }}" class="w-full"></div>
        <div><label for="description">Description</label><textarea id="description" name="description" rows="3" maxlength="2000" class="w-full">{{ old('description', $product->description) }}</textarea></div>
        <div x-data="{ previewUrl: null }">
            <template x-if="previewUrl">
                <div class="mb-2">
                    <span class="text-xs text-cocoa-500 font-semibold block mb-1">New photo preview:</span>
                    <img :src="previewUrl" alt="New package photo preview" class="w-40 h-40 object-cover rounded-lg border border-cocoa-200">
                </div>
            </template>
            @if ($product->photo_path)
                <div x-show="!previewUrl" class="mb-2">
                    <span class="text-xs text-cocoa-500 font-semibold block mb-1">Current package photo:</span>
                    <img src="{{ asset('storage/'.$product->photo_path) }}" alt="Current package photo" width="160" height="160" class="w-40 h-40 object-cover rounded-lg">
                </div>
            @endif
            <div>
                <label for="photo">Public catalog photo</label>
                <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="photo-help" @change="previewUrl = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null">
                <p id="photo-help" class="text-sm mt-1">JPG, PNG or WebP, up to 5 MB. Leave empty to keep the existing photo. Customer reference images stay with their orders.</p>
            </div>
        </div>
        <input type="hidden" name="is_active" value="0">
        <label class="flex items-center gap-3"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active))>Package available</label>
        <button class="px-5 py-3 bg-cocoa-600 text-white rounded-lg hover:bg-cocoa-700">Save package</button>
    </form>
    @if ($product->exists)
        <section class="section-form space-y-5" aria-labelledby="layer-options"><h2 id="layer-options" class="font-semibold text-cocoa-600">Layer options and inclusions</h2>
            <p class="text-sm">Select reusable items included in each package. They add ₱0 to its fixed price. Paid extras are configured separately in the item catalog. Prices use ₱0.02 increments for an exact 50% deposit. Existing orders keep their saved prices and inclusions.</p>
            @foreach ($product->options as $option)
                @include('admin.catalog.option-form', ['option' => $option])
            @endforeach

            @php($isAddingNew = old('_option') === 'new')
            @php($hasOptions = $product->options->isNotEmpty())
            <div x-data="{ showAddOption: {{ $isAddingNew || !$hasOptions ? 'true' : 'false' }} }" @close-add-option="showAddOption = false" class="pt-4 border-t border-cocoa-100">
                <div x-show="!showAddOption" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-xl bg-cream-50 border border-cocoa-200/80">
                    <div>
                        <h3 class="font-medium text-cocoa-800">Add another layer option?</h3>
                        <p class="text-xs text-cocoa-500">Only needed if this cake package offers additional sizing or tier options.</p>
                    </div>
                    <button type="button" @click="showAddOption = true" class="px-4 py-2 bg-white border border-cocoa-300 text-cocoa-700 rounded-lg hover:bg-cream-100 font-medium text-sm transition shadow-sm shrink-0">
                        + Add layer option
                    </button>
                </div>

                <div x-show="showAddOption" x-cloak class="space-y-4">
                    @include('admin.catalog.option-form', ['option' => new \App\Models\PackageOption(['is_active' => true])])
                </div>
            </div>
        </section>
    @endif
</div>
@endsection
