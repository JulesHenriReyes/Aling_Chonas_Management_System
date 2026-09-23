@extends('public.layout')

@section('title', 'Order Custom Cakes & Cupcakes - Aling Chona')

@section('content')
<div class="max-w-4xl mx-auto" x-data="{
    selectedProducts: {},
    init() {
        @if(old('items'))
            const oldItems = {{ Js::from(old('items')) }};
            Object.keys(oldItems).forEach(index => {
                const item = oldItems[index];
                if (item && item.product_id) {
                    this.selectedProducts[parseInt(item.product_id)] = {
                        id: parseInt(item.product_id),
                        quantity: item.quantity || 1
                    };
                }
            });
        @endif
    },
    toggleProduct(id, name, price) {
        if (this.selectedProducts[id]) {
            delete this.selectedProducts[id];
        } else {
            this.selectedProducts[id] = {
                id: id,
                name: name,
                price: price,
                quantity: 1,
                layers: '',
                themes: '',
                special_request: ''
            };
        }
    },
    isSelected(id) {
        return !!this.selectedProducts[id];
    },
    getSelectedCount() {
        return Object.keys(this.selectedProducts).length;
    }
}">
    <!-- Banner & Important Disclaimers -->
    <div class="bg-gradient-to-r from-rose-900 to-amber-900 text-white rounded-2xl p-6 sm:p-8 mb-8 shadow-xl">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
                <span class="inline-block px-3 py-1 bg-amber-400 text-rose-950 font-bold rounded-full text-xs uppercase tracking-wider mb-2">
                    Online Order Request
                </span>
                <h2 class="text-2xl sm:text-3xl font-extrabold text-amber-100">Order Your Custom Celebration Cake</h2>
                <p class="text-rose-100 mt-2 text-sm max-w-2xl leading-relaxed">
                    Select your favorites, specify your custom themes, layers, and decoration details, and send your request directly to Aling Chona!
                </p>
            </div>
            <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4 border border-white/20 text-xs space-y-1.5 w-full md:w-auto">
                <div class="font-bold text-amber-200 uppercase tracking-wide">Business Policy:</div>
                <div class="flex items-center gap-2"><span>📌</span> <span>Prices shown are <strong>starting base prices</strong></span></div>
                <div class="flex items-center gap-2"><span>🔍</span> <span>Customizations are reviewed by staff</span></div>
                <div class="flex items-center gap-2"><span>💳</span> <span><strong>50% Down Payment</strong> required for confirmation</span></div>
                <div class="flex items-center gap-2"><span>💵</span> <span>Cash or GCash accepted upon review</span></div>
            </div>
        </div>
    </div>

    <!-- Notice Box -->
    <div class="bg-amber-100/70 border-l-4 border-amber-500 text-amber-900 p-4 rounded-r-xl mb-8 text-sm shadow-sm">
        <div class="flex items-start gap-3">
            <span class="text-xl">⚠️</span>
            <div>
                <p class="font-semibold">How Ordering Works:</p>
                <p class="text-amber-800 text-xs mt-1 leading-relaxed">
                    Submitting this form records your order request in <strong>Pending</strong> status. Our bakery team will inspect your custom design specifications and confirm the final price. <strong>Do not send payment yet</strong>; our staff will coordinate with you to receive the 50% deposit before baking begins.
                </p>
            </div>
        </div>
    </div>

    <!-- Order Submission Form -->
    <form action="{{ route('public.order.store') }}" method="POST" enctype="multipart/form-data" class="space-y-8">
        @csrf

        <!-- STEP 1: Product Selection & Per-Product Customizations -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-stone-200">
            <div class="flex items-center justify-between border-b border-stone-200 pb-4 mb-6">
                <div>
                    <h3 class="text-lg font-bold text-stone-900 flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-rose-900 text-white text-xs flex items-center justify-center">1</span>
                        Choose Products & Customize Each Item
                    </h3>
                    <p class="text-xs text-stone-500 mt-1">Select one or more items and customize layers, themes, and design requests.</p>
                </div>
            </div>

            <!-- Product Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                @foreach ($products as $product)
                    <div class="border rounded-xl p-4 transition-all duration-200 cursor-pointer"
                         :class="isSelected({{ $product->id }}) ? 'border-rose-700 bg-rose-50/50 ring-2 ring-rose-600/20' : 'border-stone-200 hover:border-stone-300 bg-stone-50/30'"
                         @click="toggleProduct({{ $product->id }}, '{{ addslashes($product->product_name) }}', {{ $product->price }})">
                        <div class="flex items-start justify-between">
                            <div class="pr-2">
                                <h4 class="font-bold text-stone-900 text-sm">{{ $product->product_name }}</h4>
                                <p class="text-xs text-rose-700 font-semibold mt-1">
                                    Starting at ₱{{ number_format($product->price, 2) }}
                                </p>
                            </div>
                            <input type="checkbox" 
                                   :checked="isSelected({{ $product->id }})"
                                   class="rounded text-rose-900 focus:ring-rose-800 h-5 w-5 mt-0.5 pointer-events-none">
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Empty State Prompt -->
            <div x-show="getSelectedCount() === 0" class="text-center py-6 border-2 border-dashed border-stone-200 rounded-xl bg-stone-50/50">
                <p class="text-stone-500 text-sm">Please click on at least one product above to customize your order.</p>
            </div>

            <!-- Per-Product Customization Form Blocks -->
            <div x-show="getSelectedCount() > 0" class="space-y-6">
                <h4 class="font-bold text-stone-900 text-sm border-t pt-4 text-rose-950 flex items-center gap-2">
                    <span>🎨</span> Customization Details for Selected Items:
                </h4>

                @foreach ($products as $index => $product)
                    <div x-show="isSelected({{ $product->id }})" 
                         x-cloak
                         class="bg-amber-50/30 border border-amber-200 rounded-xl p-5 space-y-4">
                        <div class="flex items-center justify-between border-b border-amber-200/60 pb-3">
                            <div>
                                <h5 class="font-bold text-stone-900 text-base">{{ $product->product_name }}</h5>
                                <span class="text-xs text-amber-800 font-medium">Base Price: ₱{{ number_format($product->price, 2) }}</span>
                            </div>
                            <button type="button" 
                                    @click="toggleProduct({{ $product->id }})" 
                                    class="text-xs text-red-600 hover:text-red-800 font-medium underline">
                                Remove Item
                            </button>
                        </div>

                        <!-- Hidden Product ID input for submission -->
                        <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $product->id }}" :disabled="!isSelected({{ $product->id }})">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Quantity -->
                            <div>
                                <label class="block text-xs font-bold text-stone-700 uppercase mb-1">
                                    Quantity <span class="text-red-500">*</span>
                                </label>
                                <input type="number" 
                                       name="items[{{ $index }}][quantity]" 
                                       value="{{ old("items.{$index}.quantity", 1) }}" 
                                       min="1" 
                                       :disabled="!isSelected({{ $product->id }})"
                                       class="w-full text-sm rounded-lg border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
                            </div>

                            <!-- Layers (where applicable) -->
                            <div>
                                <label class="block text-xs font-bold text-stone-700 uppercase mb-1">
                                    Number of Layers <span class="text-stone-400 font-normal">(if cake)</span>
                                </label>
                                <input type="number" 
                                       name="items[{{ $index }}][layers]" 
                                       value="{{ old("items.{$index}.layers") }}"
                                       placeholder="e.g. 1, 2, 3" 
                                       min="1" 
                                       max="10"
                                       :disabled="!isSelected({{ $product->id }})"
                                       class="w-full text-sm rounded-lg border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
                            </div>
                        </div>

                        <!-- Theme -->
                        <div>
                            <label class="block text-xs font-bold text-stone-700 uppercase mb-1">
                                Theme / Color Palette
                            </label>
                            <input type="text" 
                                   name="items[{{ $index }}][themes]" 
                                   value="{{ old("items.{$index}.themes") }}"
                                   placeholder="e.g. Birthday / Pastel Blue & Gold, Spiderman, Floral Garden" 
                                   :disabled="!isSelected({{ $product->id }})"
                                   class="w-full text-sm rounded-lg border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
                        </div>

                        <!-- Special Request -->
                        <div>
                            <label class="block text-xs font-bold text-stone-700 uppercase mb-1">
                                Custom Inscriptions & Special Requests
                            </label>
                            <textarea name="items[{{ $index }}][special_request]" 
                                      rows="2" 
                                      placeholder="e.g. Inscription: 'Happy 7th Birthday Lucas!', Less sweet buttercream, fondant toppers..." 
                                      :disabled="!isSelected({{ $product->id }})"
                                      class="w-full text-sm rounded-lg border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">{{ old("items.{$index}.special_request") }}</textarea>
                        </div>

                        <!-- Per-Product Reference Images -->
                        <div>
                            <label class="block text-xs font-bold text-stone-700 uppercase mb-1">
                                Reference Photos for {{ $product->product_name }}
                            </label>
                            <input type="file" 
                                   name="items[{{ $index }}][images][]" 
                                   multiple 
                                   accept="image/*"
                                   :disabled="!isSelected({{ $product->id }})"
                                   class="block w-full text-xs text-stone-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-rose-50 file:text-rose-900 hover:file:bg-rose-100">
                            <p class="text-[11px] text-stone-400 mt-1">Upload reference designs or pegs (PNG, JPG, max 5MB each).</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- STEP 2: Customer Contact Information -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-stone-200">
            <div class="flex items-center justify-between border-b border-stone-200 pb-4 mb-6">
                <div>
                    <h3 class="text-lg font-bold text-stone-900 flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-rose-900 text-white text-xs flex items-center justify-center">2</span>
                        Your Contact Information
                    </h3>
                    <p class="text-xs text-stone-500 mt-1">We will use these details to coordinate confirmation and pickup.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-bold text-stone-700 uppercase mb-1">
                        First Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}" required 
                           class="w-full text-sm rounded-lg border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-stone-700 uppercase mb-1">
                        Middle Name <span class="text-stone-400 font-normal">(optional)</span>
                    </label>
                    <input type="text" name="middle_name" value="{{ old('middle_name') }}" 
                           class="w-full text-sm rounded-lg border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-stone-700 uppercase mb-1">
                        Last Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}" required 
                           class="w-full text-sm rounded-lg border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-stone-700 uppercase mb-1">
                    Mobile Phone Number <span class="text-red-500">*</span>
                </label>
                <input type="tel" name="phone_number" value="{{ old('phone_number') }}" placeholder="e.g. 0917-123-4567 or 09187654321" required 
                       class="w-full text-sm rounded-lg border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
                <p class="text-[11px] text-stone-400 mt-1">Please provide an active number where we can reach you via SMS or Call.</p>
            </div>
        </div>

        <!-- STEP 3: Pickup Schedule & Additional Notes -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-stone-200">
            <div class="flex items-center justify-between border-b border-stone-200 pb-4 mb-6">
                <div>
                    <h3 class="text-lg font-bold text-stone-900 flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-rose-900 text-white text-xs flex items-center justify-center">3</span>
                        Pickup Schedule & General Notes
                    </h3>
                    <p class="text-xs text-stone-500 mt-1">Specify when you intend to pick up your order.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-bold text-stone-700 uppercase mb-1">
                        Pickup Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="pickup_date" value="{{ old('pickup_date', now()->addDays(2)->format('Y-m-d')) }}" min="{{ date('Y-m-d') }}" required 
                           class="w-full text-sm rounded-lg border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
                    <p class="text-[11px] text-stone-400 mt-1">Please allow at least 24–48 hours for custom bakes.</p>
                </div>
                <div>
                    <label class="block text-xs font-bold text-stone-700 uppercase mb-1">
                        Pickup Time <span class="text-red-500">*</span>
                    </label>
                    <input type="time" name="pickup_time" value="{{ old('pickup_time', '14:00') }}" required 
                           class="w-full text-sm rounded-lg border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-xs font-bold text-stone-700 uppercase mb-1">
                    Overall Order Notes <span class="text-stone-400 font-normal">(optional)</span>
                </label>
                <textarea name="notes_text" rows="2" placeholder="e.g. Any dietary preferences, packaging requests, or pickup instructions..." 
                          class="w-full text-sm rounded-lg border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">{{ old('notes_text') }}</textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-stone-700 uppercase mb-1">
                    General Reference Photos <span class="text-stone-400 font-normal">(optional)</span>
                </label>
                <input type="file" name="images[]" multiple accept="image/*" 
                       class="block w-full text-xs text-stone-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-rose-50 file:text-rose-900 hover:file:bg-rose-100">
            </div>
        </div>

        <!-- Submit Button Section -->
        <div class="bg-rose-50 border border-rose-200 rounded-2xl p-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div>
                <h4 class="font-bold text-rose-950 text-base">Ready to Submit Your Request?</h4>
                <p class="text-xs text-rose-800 mt-0.5">
                    No payment is collected at this time. Our staff will review your design and verify the final price.
                </p>
            </div>
            <button type="submit" 
                    class="w-full sm:w-auto px-8 py-3.5 bg-rose-900 hover:bg-rose-800 text-white font-bold text-sm rounded-xl shadow-lg hover:shadow-xl transition transform active:scale-95 flex items-center justify-center gap-2">
                <span>🎂</span>
                <span>Submit Order Request</span>
            </button>
        </div>
    </form>
</div>
@endsection
