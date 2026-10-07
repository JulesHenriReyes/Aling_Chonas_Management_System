@extends('layouts.admin')
@section('title', 'Business GCash settings')
@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="page-heading"><h1 class="font-bold text-cocoa-600">Business GCash settings</h1><a href="{{ route('products.index') }}" class="underline">Back to catalog</a></div>
    <p>Add the bakery’s real GCash account details and QR. Buyers see these on their private payment pages. Staff must verify transactions in the business account.</p>
    <form action="{{ route('payment-settings.update') }}" method="POST" enctype="multipart/form-data" class="section-form space-y-4">
        @csrf
        <div><label for="account-name">GCash account name</label><input id="account-name" name="account_name" required maxlength="255" value="{{ old('account_name', $settings->account_name) }}" class="w-full"></div>
        <div><label for="account-number">GCash account number</label><input id="account-number" name="account_number" required maxlength="32" value="{{ old('account_number', $settings->account_number) }}" class="w-full"></div>
        <div x-data="{ previewUrl: null }">
            <template x-if="previewUrl">
                <div class="mb-2">
                    <span class="text-xs text-cocoa-500 font-semibold block mb-1">New QR image preview:</span>
                    <img :src="previewUrl" alt="New business GCash QR preview" class="w-64 max-w-full h-auto border border-cocoa-200 rounded p-1 bg-white">
                </div>
            </template>
            @if ($settings->qr_path)
                <div x-show="!previewUrl" class="mb-2">
                    <span class="text-xs text-cocoa-500 font-semibold block mb-1">Current business GCash QR:</span>
                    <img src="{{ asset('storage/'.$settings->qr_path) }}" width="256" height="256" alt="Current business GCash QR" class="w-64 max-w-full h-auto border border-cocoa-200 rounded p-1 bg-white">
                </div>
            @endif
            <div>
                <label for="qr">Business GCash QR image</label>
                <input id="qr" name="photo" type="file" accept="image/jpeg,image/png,image/webp" @required(!$settings->qr_path) @change="previewUrl = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null">
                <p class="text-sm mt-1">JPG, PNG or WebP, up to 5 MB. Use the original QR image and check that it opens the account above.</p>
            </div>
        </div>
        <button class="px-5 py-3 bg-cocoa-600 text-white rounded-lg hover:bg-cocoa-700">Save GCash settings</button>
    </form>
</div>
@endsection
