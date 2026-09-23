@extends('layouts.admin')

@section('title', 'Create Staff Order')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-stone-900">Create Staff Order</h1>
            <p class="text-xs text-stone-500 mt-1">Encode orders taken in-person, over phone, or messaging.</p>
        </div>
        <a href="{{ route('orders.index') }}" class="text-xs text-rose-900 font-bold hover:underline">
            ← Cancel & Back
        </a>
    </div>

    <form action="{{ route('orders.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <!-- Customer Selection -->
        <div class="bg-white rounded-2xl border border-stone-200 p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b pb-3">
                <h3 class="text-sm font-bold text-stone-900 uppercase tracking-wide">1. Customer Selection</h3>
                <a href="{{ route('customers.create') }}" target="_blank" class="text-xs text-rose-900 font-bold hover:underline">
                    + New Customer Record
                </a>
            </div>

            <div>
                <label class="block text-xs font-bold text-stone-700 uppercase mb-1">Select Customer <span class="text-red-500">*</span></label>
                <select name="customer_id" required class="w-full text-xs rounded-xl border-stone-300 focus:border-rose-700 focus:ring-rose-700">
                    <option value="">-- Choose Customer --</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}" {{ old('customer_id') == $c->id ? 'selected' : '' }}>
                            {{ $c->full_name }} ({{ $c->phone_number }})
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- Order Items -->
        <div class="bg-white rounded-2xl border border-stone-200 p-6 shadow-sm space-y-4"
             x-data="{
                items: [
                    { product_id: '', quantity: 1, unit_price: '', layers: '', themes: '', special_request: '' }
                ],
                products: {{ Js::from($products) }},
                addItem() {
                    this.items.push({ product_id: '', quantity: 1, unit_price: '', layers: '', themes: '', special_request: '' });
                },
                removeItem(index) {
                    if (this.items.length > 1) {
                        this.items.splice(index, 1);
                    }
                },
                updatePrice(index) {
                    const prod = this.products.find(p => p.id == this.items[index].product_id);
                    if (prod) {
                        this.items[index].unit_price = prod.price;
                    }
                }
             }">
            <div class="flex items-center justify-between border-b pb-3">
                <h3 class="text-sm font-bold text-stone-900 uppercase tracking-wide">2. Product Line Items & Customizations</h3>
                <button type="button" @click="addItem()" class="px-3 py-1 bg-stone-800 hover:bg-stone-700 text-white font-bold text-xs rounded-lg shadow">
                    + Add Another Item
                </button>
            </div>

            <template x-for="(item, index) in items" :key="index">
                <div class="border border-stone-200 rounded-xl p-4 bg-stone-50/50 space-y-3">
                    <div class="flex items-center justify-between border-b border-stone-200/60 pb-2">
                        <span class="text-xs font-bold text-stone-700" x-text="'Item #' + (index + 1)"></span>
                        <button type="button" @click="removeItem(index)" x-show="items.length > 1" class="text-xs text-red-600 hover:underline">
                            Remove
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block text-[10px] uppercase font-bold text-stone-600 mb-1">Product</label>
                            <select :name="'items[' + index + '][product_id]'" x-model="item.product_id" @change="updatePrice(index)" required class="w-full text-xs rounded-lg border-stone-300">
                                <option value="">-- Choose Product --</option>
                                @foreach ($products as $p)
                                    <option value="{{ $p->id }}">{{ $p->product_name }} (₱{{ number_format($p->price, 2) }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase font-bold text-stone-600 mb-1">Quantity</label>
                            <input type="number" min="1" :name="'items[' + index + '][quantity]'" x-model="item.quantity" required class="w-full text-xs rounded-lg border-stone-300">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[10px] uppercase font-bold text-stone-600 mb-1">Unit Price (₱)</label>
                            <input type="number" step="0.01" min="0" :name="'items[' + index + '][unit_price]'" x-model="item.unit_price" placeholder="Auto from product" class="w-full text-xs rounded-lg border-stone-300">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase font-bold text-stone-600 mb-1">Layers</label>
                            <input type="number" min="1" :name="'items[' + index + '][layers]'" x-model="item.layers" placeholder="e.g. 2" class="w-full text-xs rounded-lg border-stone-300">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase font-bold text-stone-600 mb-1">Theme / Colors</label>
                            <input type="text" :name="'items[' + index + '][themes]'" x-model="item.themes" placeholder="e.g. Blue & Gold" class="w-full text-xs rounded-lg border-stone-300">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] uppercase font-bold text-stone-600 mb-1">Special Request</label>
                        <input type="text" :name="'items[' + index + '][special_request]'" x-model="item.special_request" placeholder="e.g. Happy Birthday inscription..." class="w-full text-xs rounded-lg border-stone-300">
                    </div>
                </div>
            </template>
        </div>

        <!-- Schedule & Reference Images -->
        <div class="bg-white rounded-2xl border border-stone-200 p-6 shadow-sm space-y-4">
            <h3 class="text-sm font-bold text-stone-900 uppercase tracking-wide border-b pb-3">3. Schedule & Attachments</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-stone-700 uppercase mb-1">Pickup Date <span class="text-red-500">*</span></label>
                    <input type="date" name="pickup_date" value="{{ old('pickup_date', now()->addDays(2)->format('Y-m-d')) }}" min="{{ date('Y-m-d') }}" required class="w-full text-xs rounded-xl border-stone-300">
                </div>
                <div>
                    <label class="block text-xs font-bold text-stone-700 uppercase mb-1">Pickup Time <span class="text-red-500">*</span></label>
                    <input type="time" name="pickup_time" value="{{ old('pickup_time', '14:00') }}" required class="w-full text-xs rounded-xl border-stone-300">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-stone-700 uppercase mb-1">Internal Notes</label>
                <textarea name="notes_text" rows="2" placeholder="Any internal notes for bakers..." class="w-full text-xs rounded-xl border-stone-300">{{ old('notes_text') }}</textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-stone-700 uppercase mb-1">Reference Images</label>
                <input type="file" name="images[]" multiple accept="image/*" class="w-full text-xs text-stone-500 file:mr-2 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:bg-stone-100">
            </div>
        </div>

        <!-- Submit Button -->
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('orders.index') }}" class="px-5 py-2.5 text-xs text-stone-600 hover:text-stone-900">Cancel</a>
            <button type="submit" class="px-6 py-2.5 bg-rose-900 hover:bg-rose-800 text-white font-bold text-xs rounded-xl shadow">
                Create Order Record
            </button>
        </div>
    </form>
</div>
@endsection
