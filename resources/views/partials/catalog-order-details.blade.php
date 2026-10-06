@if ($staff)<script src="{{ asset('js/staff-customer-picker.js') }}?v={{ filemtime(public_path('js/staff-customer-picker.js')) }}"></script>@endif
<form action="{{ $staff ? route('orders.store') : route('public.order.store') }}" method="POST" class="review-first-checkout order-details-grid" @if($staff) x-data="staffCustomerPicker({{ Js::from($customers->map(fn ($customer) => ['id' => $customer->id, 'name' => $customer->full_name, 'phone' => $customer->phone_number])->values()) }}, {{ Js::from(old('customer_id', $draft['details']['customer_id'] ?? '')) }}, {{ Js::from(route('orders.inlineCustomer')) }}, {{ Js::from(csrf_token()) }})" @submit="if ($event.submitter?.formAction !== {{ Js::from(route('orders.back')) }} && !selectedId) { error = 'Choose or add a customer before saving this order.'; $event.preventDefault() }" @endif>
    @csrf
    @unless($staff)
        <input type="hidden" name="submission_key" value="{{ $draft['submission_key'] }}">
        <script defer src="{{ asset('js/public-checkout-draft.js') }}"></script>
    @endunless
    <div class="space-y-6">
        {{-- Card 1: Contact Details --}}
        <div class="checkout-card p-6 sm:p-7 space-y-5">
            <div class="flex items-center gap-2.5 pb-3 border-b border-cocoa-100">
                <div class="w-8 h-8 rounded-lg bg-cocoa-50 text-cocoa-600 flex items-center justify-center shrink-0 border border-cocoa-100">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                </div>
                <h2 class="text-base font-bold text-cocoa-700">Contact details</h2>
            </div>

            @if ($staff)
                <div class="relative" @click.outside="open = false">
                    <label for="customer-search" class="block text-xs font-semibold text-cocoa-700 uppercase tracking-wider mb-1.5">
                        Search customers by name or phone
                    </label>
                    <div class="relative">
                        <input id="customer-search" type="search" x-model="search" @focus="open = true" @click="open = true" @input="selectedId = ''; open = true" @keydown.escape="open = false" @keydown.tab="open = false" autocomplete="off" class="form-input-custom pr-10" placeholder="Type a name or phone number..." role="combobox" aria-controls="customer-options" :aria-expanded="open.toString()">
                        <input type="hidden" name="customer_id" :value="selectedId">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-cocoa-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                    </div>
                    <div id="customer-options" x-show="open && matches.length" x-cloak class="customer-results mt-1" role="listbox">
                        <template x-for="customer in matches" :key="customer.id">
                            <button type="button" role="option" class="customer-result hover:bg-cream-100 flex items-center justify-between" @click="choose(customer)">
                                <span class="font-medium text-cocoa-700" x-text="customer.name"></span>
                                <span class="text-xs text-cocoa-400" x-text="customer.phone"></span>
                            </button>
                        </template>
                    </div>
                </div>
                <div>
                    <button type="button" class="inline-flex items-center gap-1.5 text-xs font-semibold text-cocoa-600 hover:text-cocoa-700 underline" @click="showAdd = !showAdd; open = false">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        <span x-text="showAdd ? 'Close new customer form' : 'Add a new customer here'"></span>
                    </button>
                </div>
                <div x-show="showAdd" x-cloak class="border border-cocoa-100 bg-cream-50/50 rounded-xl p-4 space-y-3">
                    <h3 class="font-semibold text-sm text-cocoa-700">New customer</h3>
                    <div class="grid sm:grid-cols-2 gap-3">
                        <div><label for="new-first" class="block text-xs font-semibold text-cocoa-600 mb-1">First name</label><input id="new-first" x-model="newCustomer.first_name" maxlength="100" class="form-input-custom"></div>
                        <div><label for="new-last" class="block text-xs font-semibold text-cocoa-600 mb-1">Last name</label><input id="new-last" x-model="newCustomer.last_name" maxlength="100" class="form-input-custom"></div>
                        <div><label for="new-middle" class="block text-xs font-semibold text-cocoa-600 mb-1">Middle name (optional)</label><input id="new-middle" x-model="newCustomer.middle_name" maxlength="100" class="form-input-custom"></div>
                        <div><label for="new-phone" class="block text-xs font-semibold text-cocoa-600 mb-1">Mobile or landline number</label><input id="new-phone" type="tel" x-model="newCustomer.phone_number" maxlength="40" aria-describedby="new-phone-help new-phone-error" :aria-invalid="Boolean(fieldErrors.phone_number)" class="form-input-custom"><p id="new-phone-help" class="text-xs text-cocoa-500 mt-1">For landlines, include the area code, e.g. 032 234 5678.</p><p id="new-phone-error" role="alert" x-show="fieldErrors.phone_number" x-text="fieldErrors.phone_number?.[0]" class="text-sm text-red-700" x-cloak></p></div>
                    </div>
                    <button type="button" @click="createCustomer()" :disabled="creating" class="px-4 py-2 bg-cocoa-600 hover:bg-cocoa-700 text-white text-xs font-semibold rounded-lg disabled:opacity-60 transition" x-text="creating ? 'Saving customer…' : 'Save and select customer'"></button>
                </div>
                <p role="alert" x-show="error" x-text="error" class="text-xs text-red-700 bg-red-50 p-2.5 rounded-lg border border-red-200" x-cloak></p>
            @else
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label for="first-name" class="block text-xs font-semibold text-cocoa-700 uppercase tracking-wider mb-1.5">
                            First name <span class="text-red-500">*</span>
                        </label>
                        <input id="first-name" name="first_name" autocomplete="given-name" required maxlength="100" value="{{ old('first_name', $draft['details']['first_name'] ?? '') }}" class="form-input-custom" placeholder="Maria">
                    </div>
                    <div>
                        <label for="last-name" class="block text-xs font-semibold text-cocoa-700 uppercase tracking-wider mb-1.5">
                            Last name <span class="text-red-500">*</span>
                        </label>
                        <input id="last-name" name="last_name" autocomplete="family-name" required maxlength="100" value="{{ old('last_name', $draft['details']['last_name'] ?? '') }}" class="form-input-custom" placeholder="Santos">
                    </div>
                    <div>
                        <label for="middle-name" class="block text-xs font-semibold text-cocoa-700 uppercase tracking-wider mb-1.5">
                            Middle name <span class="text-[11px] text-cocoa-400 font-normal normal-case">(optional)</span>
                        </label>
                        <input id="middle-name" name="middle_name" autocomplete="additional-name" maxlength="100" value="{{ old('middle_name', $draft['details']['middle_name'] ?? '') }}" class="form-input-custom" placeholder="Cruz">
                    </div>
                    <div>
                        <label for="phone" class="block text-xs font-semibold text-cocoa-700 uppercase tracking-wider mb-1.5">
                            Mobile or landline number <span class="text-red-500">*</span>
                        </label>
                        <input id="phone" name="phone_number" type="tel" autocomplete="tel" required maxlength="40" aria-describedby="phone-help phone-error" @error('phone_number') aria-invalid="true" @enderror value="{{ old('phone_number', $draft['details']['phone_number'] ?? '') }}" class="form-input-custom" placeholder="0917 123 4567">
                        <p id="phone-help" class="text-xs text-cocoa-500 mt-1">For landlines, include the area code, e.g. 02 8123 4567 or 032 234 5678. +63 numbers are accepted.</p>
                        @error('phone_number')<p id="phone-error" role="alert" class="text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                </div>
            @endif
        </div>

        {{-- Card 2: Pickup Details --}}
        <div class="checkout-card p-6 sm:p-7 space-y-5">
            <div class="flex items-center gap-2.5 pb-3 border-b border-cocoa-100">
                <div class="w-8 h-8 rounded-lg bg-cocoa-50 text-cocoa-600 flex items-center justify-center shrink-0 border border-cocoa-100">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                <h2 class="text-base font-bold text-cocoa-700">Pickup details</h2>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label for="pickup-date" class="block text-xs font-semibold text-cocoa-700 uppercase tracking-wider mb-1.5">
                        Pickup date <span class="text-red-500">*</span>
                    </label>
                    <input id="pickup-date" name="pickup_date" type="date" min="{{ \App\Support\PickupCalendar::todayString() }}" required value="{{ old('pickup_date', $draft['details']['pickup_date'] ?? '') }}" class="form-input-custom">
                </div>
                <div>
                    <label for="pickup-time" class="block text-xs font-semibold text-cocoa-700 uppercase tracking-wider mb-1.5">
                        Pickup time <span class="text-red-500">*</span>
                    </label>
                    <input id="pickup-time" name="pickup_time" type="time" required value="{{ old('pickup_time', $draft['details']['pickup_time'] ?? '') }}" class="form-input-custom">
                </div>
            </div>

            <div>
                <label for="notes" class="block text-xs font-semibold text-cocoa-700 uppercase tracking-wider mb-1.5">
                    {{ $staff ? 'Internal order notes' : 'Order notes' }} <span class="text-[11px] text-cocoa-400 font-normal normal-case">(optional)</span>
                </label>
                <textarea id="notes" name="notes_text" maxlength="1000" rows="3" class="form-input-custom" placeholder="Optional notes or instructions...">{{ old('notes_text', $draft['details']['notes_text'] ?? '') }}</textarea>
            </div>
        </div>
    </div>

    {{-- Order Summary Aside --}}
    <aside class="checkout-card p-6 sm:p-7 space-y-5 lg:sticky lg:top-24" aria-labelledby="order-summary">
        <div class="flex items-center justify-between pb-3.5 border-b border-cocoa-100">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-cocoa-600 text-white flex items-center justify-center shrink-0 shadow-xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                </div>
                <h2 id="order-summary" class="font-bold text-cocoa-700 text-base">Order summary</h2>
            </div>
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-cream-100 text-cocoa-600 border border-cocoa-100">
                {{ count($quote['lines']) }} {{ count($quote['lines']) === 1 ? 'item' : 'items' }}
            </span>
        </div>

        <div class="divide-y divide-cocoa-100 max-h-96 overflow-y-auto pr-1 -mr-1">
            @foreach ($quote['lines'] as $line)
                @php($photo = $line['photo_path'] ?? \App\Models\Product::find($line['product_id'])?->photo_path)
                @php($draftItem = $draft['items'][$loop->index] ?? null)
                <div class="py-3.5 text-xs space-y-2.5 first:pt-0">
                    {{-- Package visual row with thumbnail on left --}}
                    <div class="flex items-start gap-3">
                        @if ($photo)
                            <img src="{{ asset('storage/' . $photo) }}"
                                 alt="{{ $line['product_name_snapshot'] }}"
                                 width="64"
                                 height="64"
                                 class="w-16 h-16 rounded-xl object-cover border border-cocoa-100/90 bg-white shrink-0 shadow-xs">
                        @else
                            <div class="w-16 h-16 rounded-xl bg-cocoa-50 border border-cocoa-100 flex items-center justify-center shrink-0 text-cocoa-400">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8.25v-1.5m0 1.5c-1.355 0-2.697.056-4.024.166C6.845 8.51 6 9.473 6 10.608v2.513m6-4.871c1.355 0 2.697.056 4.024.166C17.155 8.51 18 9.473 18 10.608v2.513M15 8.25v-1.5m-6 1.5v-1.5m12 9.75-1.5.75m-15-.75 1.5.75m0 0a3.75 3.75 0 0 0 7.5 0m7.5 0a3.75 3.75 0 0 1-7.5 0" /></svg>
                            </div>
                        @endif

                        <div class="min-w-0 flex-1 space-y-0.5">
                            <div class="flex justify-between items-start gap-2">
                                <div class="font-bold text-cocoa-700 text-sm leading-snug">
                                    {{ $line['product_name_snapshot'] }}
                                    <span class="inline-block text-xs font-semibold text-cocoa-600 bg-cream-100 px-1.5 py-0.5 rounded border border-cocoa-100 ml-1">×{{ $line['quantity'] }}</span>
                                </div>
                                <span class="font-bold text-cocoa-700 whitespace-nowrap text-sm">₱{{ number_format($line['unit_price'] * $line['quantity'], 2) }}</span>
                            </div>
                            <p class="text-cocoa-500 text-[11px]">{{ $line['layers'] }} layer(s)</p>
                        </div>
                    </div>

                    @include('partials.included-items', ['includedItems' => $line['included_items_snapshot'] ?? [], 'includedText' => $line['included_contents_snapshot'], 'packageQuantity' => $line['quantity']])

                    @if ($line['themes'])
                        <div class="text-[11px] text-cocoa-600 bg-cream-100/60 px-2 py-1 rounded border border-cocoa-100/80">
                            <span class="font-semibold text-cocoa-700">Theme/Colors:</span> {{ $line['themes'] }}
                        </div>
                    @endif
                    @if ($line['special_request'])
                        <div class="text-[11px] text-cocoa-600 bg-amber-50/60 px-2 py-1 rounded border border-amber-200/60">
                            <span class="font-semibold text-amber-900">Custom request:</span> {{ $line['special_request'] }}
                        </div>
                    @endif
                    @if (!empty($draftItem['staged_images']))
                        <div class="text-[11px] text-cocoa-600 bg-cream-50 px-2 py-1 rounded border border-cocoa-100 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-cocoa-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <span>{{ count($draftItem['staged_images']) }} reference photo(s) attached</span>
                        </div>
                    @endif
                    @if (!empty($line['add_ons']))
                        <div class="pl-2 border-l-2 border-cocoa-200 text-[11px] space-y-1.5 text-cocoa-500 pt-0.5">
                            <p class="font-semibold text-cocoa-600">Paid extras · quantities for this whole order line</p>
                            @foreach ($line['add_ons'] as $extra)
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        @if (!empty($extra['photo_path']))
                                            <img src="{{ asset('storage/' . $extra['photo_path']) }}"
                                                 alt="{{ $extra['name_snapshot'] }}"
                                                 class="w-6 h-6 rounded object-cover border border-cocoa-100 shrink-0 bg-cream-50">
                                        @endif
                                        <span class="truncate">+ {{ $extra['name_snapshot'] }} × {{ $extra['quantity'] }}</span>
                                    </div>
                                    <span class="font-medium text-cocoa-600 whitespace-nowrap">₱{{ number_format($extra['unit_price'] * $extra['quantity'], 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="border-t border-cocoa-100 pt-3.5 space-y-3">
            <div class="flex justify-between items-center text-sm">
                <span class="text-cocoa-500 font-medium">Total Price</span>
                <span class="font-bold text-cocoa-700 text-lg">₱{{ number_format($quote['total'], 2) }}</span>
            </div>

            <div class="p-3.5 rounded-xl bg-cocoa-50/80 border border-cocoa-200/80 space-y-1">
                <div class="flex justify-between items-baseline">
                    <span class="text-xs font-bold text-cocoa-700 uppercase tracking-wide inline-flex items-center gap-1">
                        Exact 50% Deposit
                        <x-tooltip text="A 50% deposit is required to reserve your order once staff confirms availability. The remaining balance is paid at pickup." />
                    </span>
                    <strong class="text-base font-extrabold text-cocoa-700">₱{{ number_format($quote['deposit'], 2) }}</strong>
                </div>
                <p class="text-[11px] text-cocoa-500 leading-tight">
                    Pay only after staff confirms your request. The verified deposit secures your booking; the remaining balance (₱{{ number_format($quote['total'] - $quote['deposit'], 2) }}) is paid at actual pickup.
                </p>
            </div>
        </div>

        <input type="hidden" name="expected_total" value="{{ $quote['total'] }}">

        <p class="text-xs text-cocoa-500">The bakery will review your request first. Payment becomes available after staff confirmation.</p>

        <div class="flex flex-col gap-2 pt-1">
            <button type="submit" class="w-full py-3.5 px-5 bg-cocoa-600 hover:bg-cocoa-700 active:scale-[0.99] text-white font-semibold rounded-xl shadow-sm transition flex items-center justify-center gap-2 text-sm">
                <span>{{ $staff ? 'Create staff order request' : 'Submit order request' }}</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
            </button>
            <button type="submit" formnovalidate formaction="{{ $staff ? route('orders.back') : route('public.order.back') }}" class="w-full py-2.5 px-4 border border-cocoa-200 hover:bg-cream-100 text-cocoa-600 font-medium rounded-xl transition flex items-center justify-center gap-2 text-xs">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                <span>Back to packages</span>
            </button>
        </div>
    </aside>
</form>
