@if ($staff)<script src="{{ asset('js/staff-customer-picker.js') }}?v={{ filemtime(public_path('js/staff-customer-picker.js')) }}"></script>@endif
<form action="{{ $staff ? route('orders.store') : route('public.order.store') }}" method="POST" class="order-details-grid" @if($staff) x-data="staffCustomerPicker({{ Js::from($customers->map(fn ($customer) => ['id' => $customer->id, 'name' => $customer->full_name, 'phone' => $customer->phone_number])->values()) }}, {{ Js::from(old('customer_id', $draft['details']['customer_id'] ?? '')) }}, {{ Js::from(route('orders.inlineCustomer')) }}, {{ Js::from(csrf_token()) }})" @submit="if ($event.submitter?.formAction !== {{ Js::from(route('orders.back')) }} && !selectedId) { error = 'Choose or add a customer before saving this order.'; $event.preventDefault() }" @endif>
    @csrf
    <div class="section-form space-y-5">
        <h2 class="font-semibold text-cocoa-600">Contact details</h2>
        @if ($staff)
            <div class="relative" @click.outside="open = false">
                <label for="customer-search">Search customers by name or phone</label>
                <input id="customer-search" type="search" x-model="search" @focus="open = true" @click="open = true" @input="selectedId = ''; open = true" @keydown.escape="open = false" @keydown.tab="open = false" autocomplete="off" class="w-full" placeholder="Type a name or phone number" role="combobox" aria-controls="customer-options" :aria-expanded="open.toString()">
                <input type="hidden" name="customer_id" :value="selectedId">
                <div id="customer-options" x-show="open && matches.length" x-cloak class="customer-results" role="listbox">
                    <template x-for="customer in matches" :key="customer.id"><button type="button" role="option" class="customer-result" @click="choose(customer)" x-text="customer.name + ' · ' + customer.phone"></button></template>
                </div>
            </div>
            <button type="button" class="underline font-medium" @click="showAdd = !showAdd; open = false" x-text="showAdd ? 'Close new customer' : 'Add a customer here'"></button>
            <div x-show="showAdd" x-cloak class="border-t border-cocoa-100 pt-4 space-y-3">
                <h3 class="font-semibold">New customer</h3>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div><label for="new-first">First name</label><input id="new-first" x-model="newCustomer.first_name" maxlength="100" class="w-full"></div>
                    <div><label for="new-last">Last name</label><input id="new-last" x-model="newCustomer.last_name" maxlength="100" class="w-full"></div>
                    <div><label for="new-middle">Middle name (optional)</label><input id="new-middle" x-model="newCustomer.middle_name" maxlength="100" class="w-full"></div>
                    <div><label for="new-phone">Phone number</label><input id="new-phone" type="tel" x-model="newCustomer.phone_number" maxlength="20" class="w-full"></div>
                </div>
                <button type="button" @click="createCustomer()" :disabled="creating" class="px-4 py-2 bg-cocoa-600 text-white rounded-lg disabled:opacity-60" x-text="creating ? 'Saving customer…' : 'Save and select customer'"></button>
            </div>
            <p role="alert" x-show="error" x-text="error" class="text-red-800" x-cloak></p>
        @else
            <div class="grid sm:grid-cols-2 gap-4">
                <div><label for="first-name">First name</label><input id="first-name" name="first_name" autocomplete="given-name" required maxlength="100" value="{{ old('first_name', $draft['details']['first_name'] ?? '') }}" class="w-full"></div>
                <div><label for="last-name">Last name</label><input id="last-name" name="last_name" autocomplete="family-name" required maxlength="100" value="{{ old('last_name', $draft['details']['last_name'] ?? '') }}" class="w-full"></div>
                <div><label for="middle-name">Middle name (optional)</label><input id="middle-name" name="middle_name" autocomplete="additional-name" maxlength="100" value="{{ old('middle_name', $draft['details']['middle_name'] ?? '') }}" class="w-full"></div>
                <div><label for="phone">Phone number</label><input id="phone" name="phone_number" type="tel" autocomplete="tel" required minlength="7" maxlength="20" value="{{ old('phone_number', $draft['details']['phone_number'] ?? '') }}" class="w-full"></div>
            </div>
        @endif
        <div class="border-t border-cocoa-100 pt-5 space-y-4">
            <h2 class="font-semibold text-cocoa-600">Pickup details</h2>
            <div class="grid sm:grid-cols-2 gap-4">
                <div><label for="pickup-date">Pickup date</label><input id="pickup-date" name="pickup_date" type="date" min="{{ today()->toDateString() }}" required value="{{ old('pickup_date', $draft['details']['pickup_date'] ?? '') }}" class="w-full"></div>
                <div><label for="pickup-time">Pickup time</label><input id="pickup-time" name="pickup_time" type="time" required value="{{ old('pickup_time', $draft['details']['pickup_time'] ?? '') }}" class="w-full"></div>
            </div>
            <p class="text-sm">Pickup times use {{ config('bakery.pickup_timezone') }}. The order must be ready by this deadline; the remaining balance is due at pickup.</p>
            <div><label for="notes">{{ $staff ? 'Internal order notes' : 'Order notes' }} (optional)</label><textarea id="notes" name="notes_text" maxlength="1000" rows="3" class="w-full">{{ old('notes_text', $draft['details']['notes_text'] ?? '') }}</textarea></div>
        </div>
    </div>
    <aside class="section-form space-y-4" aria-labelledby="order-summary">
        <h2 id="order-summary" class="font-semibold text-cocoa-600">Order summary</h2>
        @foreach ($quote['lines'] as $line)
            <div class="border-t border-cocoa-100 pt-3 text-sm space-y-1">
                <div class="flex justify-between gap-3"><strong>{{ $line['product_name_snapshot'] }} × {{ $line['quantity'] }}</strong><span class="whitespace-nowrap">₱{{ number_format($line['unit_price'] * $line['quantity'], 2) }}</span></div>
                <p>{{ $line['layers'] }} layer(s) · {{ $line['included_contents_snapshot'] }}</p>
                @if ($line['themes'])<p>Theme and colors: {{ $line['themes'] }}</p>@endif
                @if ($line['special_request'])<p>Design request: {{ $line['special_request'] }}</p>@endif
                @foreach ($line['add_ons'] as $extra)<p>Extra: {{ $extra['name_snapshot'] }} × {{ $extra['quantity'] }} · ₱{{ number_format($extra['unit_price'] * $extra['quantity'], 2) }}</p>@endforeach
            </div>
        @endforeach
        <div class="border-t border-cocoa-100 pt-3 flex justify-between gap-3 text-lg font-bold"><span>Total</span><span>₱{{ number_format($quote['total'], 2) }}</span></div>
        <div class="flex justify-between gap-3"><span>Exact 50% deposit</span><strong>₱{{ number_format($quote['deposit'], 2) }}</strong></div>
        <input type="hidden" name="expected_total" value="{{ $quote['total'] }}">
        <p class="text-sm">{{ $staff ? 'Record the exact deposit after creating this order.' : 'After ordering, use the business GCash QR and submit your receipt. Staff confirm the order after verifying payment.' }}</p>
        <p class="text-sm">Customer cancellations retain the deposit. If the bakery misses the pickup deadline, all verified payments are refundable.</p>
        <div class="flex flex-col gap-2">
            <button type="submit" class="px-5 py-3 bg-cocoa-600 text-white rounded-lg hover:bg-cocoa-700">{{ $staff ? 'Create staff order' : 'Submit order' }}</button>
            <button type="submit" formnovalidate formaction="{{ $staff ? route('orders.back') : route('public.order.back') }}" class="px-5 py-2 border border-cocoa-200 rounded-lg">Back to packages</button>
        </div>
    </aside>
</form>
