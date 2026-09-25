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
        @if ($settings->qr_path)<img src="{{ asset('storage/'.$settings->qr_path) }}" width="256" height="256" alt="Current business GCash QR" class="w-64 max-w-full h-auto">@endif
        <div><label for="qr">Business GCash QR image</label><input id="qr" name="photo" type="file" accept="image/jpeg,image/png,image/webp" @required(!$settings->qr_path)><p class="text-sm mt-1">JPG, PNG or WebP, up to 5 MB. Use the original QR image and check that it opens the account above.</p></div>
        <button class="px-5 py-3 bg-cocoa-600 text-white rounded-lg hover:bg-cocoa-700">Save GCash settings</button>
    </form>
</div>
@endsection
