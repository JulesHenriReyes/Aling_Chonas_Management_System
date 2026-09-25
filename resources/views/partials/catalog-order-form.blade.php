<script src="{{ asset('js/catalog-order.js') }}?v={{ filemtime(public_path('js/catalog-order.js')) }}"></script>
<form action="{{ $staff ? route('orders.store') : route('public.order.store') }}" method="POST" enctype="multipart/form-data"
    x-data="catalogOrder({{ Js::from($products) }}, {{ Js::from(old('items', [])) }}, {{ Js::from(route('public.order.quote')) }})" @submit.prevent="submit($event)" class="space-y-6">
    @csrf
    <noscript><p>Enable JavaScript to select packages and review your itemized total before ordering.</p></noscript>
    <section class="section-form space-y-5" aria-labelledby="choose-packages">
        <h2 id="choose-packages" class="font-semibold text-cocoa-600">1. Choose and customize</h2>
        <p class="text-sm">Each layer option has a fixed price and listed inclusions. Themes, design requests and reference photos do not change that price.</p>
        <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-5">
            @forelse ($products as $product)
                <article class="border-b border-cocoa-100 pb-4 space-y-3">
                    @if ($product->photo_path)<img src="{{ asset('storage/'.$product->photo_path) }}" alt="{{ $product->product_name }}" width="360" height="240" loading="lazy" class="w-full h-44 object-cover rounded-lg">@endif
                    <h3 class="font-semibold text-cocoa-600">{{ $product->product_name }}</h3>
                    @if ($product->description)<p class="text-sm">{{ $product->description }}</p>@endif
                    <p class="text-sm">From ₱{{ number_format($product->options->min('price'), 2) }} · {{ $product->options->pluck('layers')->join(', ') }} layer option(s)</p>
                    <button type="button" @click="add(products.find(product => product.id === {{ $product->id }}))" class="px-4 py-2 border border-cocoa-200 rounded-lg hover:bg-cream-100" aria-label="Add {{ $product->product_name }}">Add package</button>
                </article>
            @empty
                <p class="sm:col-span-2">Cake packages are being updated. Please contact the bakery or check back once layer options and prices are available.</p>
            @endforelse
        </div>
        <template x-for="(item, index) in items" :key="item.uid">
            <section class="item-section space-y-4" :aria-label="'Selected package ' + (index + 1)">
                <div class="page-heading"><h3 class="font-semibold text-cocoa-600" x-text="product(item)?.product_name || 'Unavailable package'"></h3><button type="button" @click="items.splice(index, 1)" class="underline px-3 py-2" :aria-label="'Remove package ' + (index + 1)">Remove</button></div>
                <input type="hidden" :name="'items['+index+'][product_id]'" :value="item.product_id">
                <div class="grid sm:grid-cols-[2fr_1fr] gap-4">
                    <div><label :for="'option-'+item.uid">Layer option and fixed price</label>
                        <select :id="'option-'+item.uid" :name="'items['+index+'][package_option_id]'" x-model="item.package_option_id" required class="w-full">
                            <option value="">Choose an option</option><template x-for="option in (product(item)?.options || [])" :key="option.id"><option :value="option.id" x-text="option.layers + ' layer(s) · ' + money(option.price)"></option></template>
                        </select></div>
                    <div><label :for="'quantity-'+item.uid">Packages</label><input :id="'quantity-'+item.uid" :name="'items['+index+'][quantity]'" type="number" min="1" max="999" step="1" x-model="item.quantity" required class="w-full"></div>
                </div>
                <p class="text-sm"><strong>Included per package:</strong> <span class="whitespace-pre-line" x-text="option(item)?.included_contents || 'Choose an available layer option.'"></span></p>
                <fieldset class="space-y-3"><legend class="font-semibold mb-2">Paid extras (optional)</legend>
                    <p class="text-sm">Extra quantities are for this entire package line, in addition to the contents included above.</p>
                    <template x-for="extra in (product(item)?.add_ons || [])" :key="extra.id">
                        <div class="border-t border-cocoa-100 pt-3 space-y-3">
                            <div class="flex gap-3 items-start">
                                <template x-if="extra.photo_path"><img :src="'/storage/'+extra.photo_path" :alt="extra.name" width="80" height="80" loading="lazy" class="w-20 h-20 object-cover rounded-lg"></template>
                                <label class="flex items-start gap-3 flex-1"><input type="checkbox" :checked="!!selectedExtra(item, extra.id)" @change="toggleExtra(item, extra, $event.target.checked)"><span><strong x-text="extra.name + ' · ' + money(extra.price)"></strong><span class="block text-sm" x-text="extra.description"></span></span></label>
                            </div>
                            <template x-if="selectedExtra(item, extra.id)"><div>
                                <input type="hidden" :name="'items['+index+'][add_ons]['+item.add_ons.indexOf(selectedExtra(item, extra.id))+'][add_on_id]'" :value="extra.id">
                                <label :for="'extra-'+item.uid+'-'+extra.id" x-text="extra.name+' quantity'"></label>
                                <input :id="'extra-'+item.uid+'-'+extra.id" :name="'items['+index+'][add_ons]['+item.add_ons.indexOf(selectedExtra(item, extra.id))+'][quantity]'" type="number" min="1" max="999" step="1" x-model="selectedExtra(item, extra.id).quantity" required class="w-full sm:max-w-40">
                            </div></template>
                        </div>
                    </template>
                    <p x-show="!(product(item)?.add_ons.length)" class="text-sm">No paid extras for this package.</p>
                </fieldset>
                <div><label :for="'theme-'+item.uid">Theme and colors (optional)</label><input :id="'theme-'+item.uid" :name="'items['+index+'][themes]'" maxlength="255" x-model="item.themes" class="w-full"></div>
                <div><label :for="'request-'+item.uid">Design instructions and special requests (optional)</label><textarea :id="'request-'+item.uid" :name="'items['+index+'][special_request]'" rows="2" maxlength="1000" x-model="item.special_request" class="w-full"></textarea></div>
                <div><label :for="'images-'+item.uid">Design reference photos for this package (optional)</label><input :id="'images-'+item.uid" :name="'items['+index+'][images][]'" type="file" multiple accept="image/jpeg,image/png,image/webp"><p class="text-sm mt-1">Up to 5 JPG, PNG or WebP images, 5 MB each. These are design references, not payment receipts.</p></div>
                <p class="font-semibold text-right" x-text="'Package line total: ' + money(lineTotal(item))"></p>
            </section>
        </template>
    </section>

    <div class="grid lg:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)] gap-6 items-start">
        <div class="section-form space-y-6">
            <section class="form-section space-y-4" aria-labelledby="contact-details">
                <h2 id="contact-details" class="font-semibold text-cocoa-600">2. Contact details</h2>
                @if ($staff)
                    <div><label for="customer">Customer</label><select id="customer" name="customer_id" required class="w-full"><option value="">Choose a customer</option>@foreach ($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->full_name }} ({{ $customer->phone_number }})</option>@endforeach</select></div>
                    <a href="{{ route('customers.create') }}" target="_blank" rel="noopener" class="inline-block underline">Add a customer (opens a new tab)</a>
                @else
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div><label for="first-name">First name</label><input id="first-name" name="first_name" autocomplete="given-name" required maxlength="100" value="{{ old('first_name') }}" class="w-full"></div>
                        <div><label for="last-name">Last name</label><input id="last-name" name="last_name" autocomplete="family-name" required maxlength="100" value="{{ old('last_name') }}" class="w-full"></div>
                        <div><label for="middle-name">Middle name (optional)</label><input id="middle-name" name="middle_name" autocomplete="additional-name" maxlength="100" value="{{ old('middle_name') }}" class="w-full"></div>
                        <div><label for="phone">Phone number</label><input id="phone" name="phone_number" type="tel" autocomplete="tel" required minlength="7" maxlength="20" value="{{ old('phone_number') }}" class="w-full"></div>
                    </div>
                @endif
            </section>
            <section class="form-section space-y-4" aria-labelledby="pickup-details">
                <h2 id="pickup-details" class="font-semibold text-cocoa-600">3. Pickup and notes</h2>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div><label for="pickup-date">Pickup date</label><input id="pickup-date" name="pickup_date" type="date" min="{{ today()->toDateString() }}" required value="{{ old('pickup_date') }}" class="w-full"></div>
                    <div><label for="pickup-time">Pickup time</label><input id="pickup-time" name="pickup_time" type="time" required value="{{ old('pickup_time') }}" class="w-full"></div>
                </div>
                <p class="text-sm">Pickup times use {{ config('bakery.pickup_timezone') }}. The bakery must have the order ready by this date and time. The remaining balance is due at pickup.</p>
                <div><label for="notes">{{ $staff ? 'Internal order notes' : 'Order notes' }} (optional)</label><textarea id="notes" name="notes_text" maxlength="1000" rows="3" class="w-full">{{ old('notes_text') }}</textarea></div>
                <div><label for="order-images">General design reference photos (optional)</label><input id="order-images" name="images[]" type="file" multiple accept="image/jpeg,image/png,image/webp"><p class="text-sm mt-1">Up to 5 images, 5 MB each. Upload payment receipts after submitting your order.</p></div>
            </section>
        </div>
        <section class="section-form space-y-4" aria-labelledby="order-total" x-ref="review" tabindex="-1">
            <h2 id="order-total" class="font-semibold text-cocoa-600">Order summary</h2>
            <template x-for="item in items" :key="item.uid"><div class="text-sm flex justify-between gap-3"><span x-text="(product(item)?.product_name || 'Package')+' × '+item.quantity"></span><span class="whitespace-nowrap" x-text="money(lineTotal(item))"></span></div></template>
            <p x-show="!items.length">Choose a package to get started.</p>
            <div class="flex justify-between gap-3 text-lg font-bold"><span>Total</span><span x-text="money(quote ? quote.total : total())"></span></div>
            <div class="flex justify-between gap-3"><span>Exact 50% deposit</span><span x-text="money(quote ? quote.deposit : total()/2)"></span></div>
            <template x-if="quote"><div class="border-t border-cocoa-100 pt-4 space-y-3">
                <h3 class="font-semibold">Reviewed itemized total</h3>
                <template x-for="(line, quoteIndex) in quote.lines" :key="quoteIndex"><div class="space-y-1 text-sm">
                    <p class="font-semibold" x-text="line.product_name_snapshot+' · '+line.layers+' layer(s) × '+line.quantity+' · '+money(line.unit_price*line.quantity)"></p>
                    <p x-text="'Included per package: '+line.included_contents_snapshot"></p>
                    <template x-for="extra in line.add_ons" :key="extra.add_on_id"><p x-text="'Paid extra: '+extra.name_snapshot+' × '+extra.quantity+' · '+money(extra.unit_price*extra.quantity)"></p></template>
                </div></template>
                <p role="status">Prices checked. Review the itemized total, then submit.</p>
            </div></template>
            <input type="hidden" name="expected_total" :value="quote ? quote.total : ''">
            <p class="text-sm">{{ $staff ? 'Record the exact deposit after saving to confirm the order. Cash and GCash are accepted for staff-created orders.' : 'After submission, pay the deposit using the business GCash QR and send your receipt. The order is confirmed only after staff verifies it.' }}</p>
            <p class="text-sm">Customer cancellations retain the deposit. If the bakery cannot fulfill the order by pickup, all verified payments are refundable.</p>
            <p role="alert" x-show="error" x-text="error" class="text-red-800" x-cloak></p>
            <button type="submit" :disabled="busy" class="w-full px-4 py-3 bg-cocoa-600 text-white rounded-lg hover:bg-cocoa-700 disabled:opacity-60" x-text="busy ? 'Checking…' : (quote ? '{{ $staff ? 'Create staff order' : 'Submit order' }}' : 'Review itemized total')">Review itemized total</button>
        </section>
    </div>
</form>
