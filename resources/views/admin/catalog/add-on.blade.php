@extends('layouts.admin')
@section('title', $addOn->exists ? 'Edit add-on' : 'Add an add-on')
@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="page-heading"><h1 class="font-bold text-cocoa-600">{{ $addOn->exists ? 'Edit add-on' : 'Add an add-on' }}</h1><a href="{{ route('products.index') }}" class="ui-button">Back to catalog</a></div>
    <form action="{{ $addOn->exists ? route('add-ons.update', $addOn) : route('add-ons.store') }}" method="POST" enctype="multipart/form-data" class="section-form space-y-4">
        @csrf @if ($addOn->exists) @method('PATCH') @endif
        <input type="hidden" name="_add_on" value="{{ $addOn->id ?? 'new' }}">
        <div><label for="name">Add-on name</label><input id="name" name="name" required maxlength="255" value="{{ old('name', $addOn->name) }}" class="w-full"></div>
        <div><label for="description">Purpose and contents</label><textarea id="description" name="description" rows="3" maxlength="2000" required class="w-full">{{ old('description', $addOn->description) }}</textarea><p class="text-sm mt-1">Explain what one add-on contains and how it adds to the package’s included contents.</p></div>
        <div><label for="price">Fixed price per add-on (₱)</label><input id="price" name="price" type="number" step="0.02" min="0.02" max="999999.98" required value="{{ old('price', $addOn->price) }}" class="w-full"><p class="text-sm mt-1">Use ₱0.02 increments to support an exact 50% deposit.</p></div>
        <div x-data="{ previewUrl: null }">
            <template x-if="previewUrl">
                <div class="mb-2">
                    <span class="text-xs text-cocoa-500 font-semibold block mb-1">New photo preview:</span>
                    <img :src="previewUrl" alt="New add-on photo preview" class="w-40 h-40 object-cover rounded-lg border border-cocoa-200">
                </div>
            </template>
            @if ($addOn->photo_path)
                <div x-show="!previewUrl" class="mb-2">
                    <span class="text-xs text-cocoa-500 font-semibold block mb-1">Current add-on photo:</span>
                    <img src="{{ asset('storage/'.$addOn->photo_path) }}" alt="Current add-on photo" width="160" height="160" class="w-40 h-40 object-cover rounded-lg">
                </div>
            @endif
            <div>
                <label for="photo">Public catalog photo</label>
                <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" @change="previewUrl = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null">
                <p class="text-sm mt-1">JPG, PNG or WebP, up to 5 MB. Leave empty to keep the existing photo.</p>
            </div>
        </div>
        <fieldset class="space-y-3"><legend class="font-semibold mb-3">Offer this paid extra with</legend>
            <p class="text-sm">Only selected packages offer this item as a paid extra at its normal price. This does not control package visibility or included items. Leave all unchecked to use it only in package inclusions.</p>
            @foreach ($products as $product)<label class="flex items-center gap-3"><input type="checkbox" name="products[]" value="{{ $product->id }}" @checked(in_array($product->id, old('_add_on') === (string) ($addOn->id ?? 'new') ? old('products', []) : $addOn->products->modelKeys()))>{{ $product->product_name }}</label>@endforeach
            @if ($products->isEmpty())<p>No cake packages yet. You can still save this reusable item.</p>@endif
        </fieldset>
        <input type="hidden" name="is_active" value="0">
        <label class="flex items-center gap-3"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $addOn->is_active))>Available as a paid extra</label>
        <p class="text-sm">Included items remain part of their layer options even when this paid extra is unavailable.</p>
        <button class="px-5 py-3 bg-cocoa-600 text-white rounded-lg hover:bg-cocoa-700">Save add-on</button>
    </form>
</div>
@endsection
