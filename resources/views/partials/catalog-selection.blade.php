<script src="{{ asset('js/catalog-order.js') }}?v={{ filemtime(public_path('js/catalog-order.js')) }}"></script>
<form action="{{ $staff ? route('orders.continue') : route('public.order.continue') }}" method="POST" enctype="multipart/form-data"
    x-data="catalogOrder({{ Js::from($products) }}, {{ Js::from(old('items', $draft['items'] ?? [])) }}, {{ Js::from(route('public.order.quote')) }}, {{ $staff ? 'true' : 'false' }})" @submit="if (!items.length) { error = 'Add at least one cake package.'; $event.preventDefault() }" class="space-y-6">
    @csrf
    <noscript><p>Enable JavaScript to select packages and review your itemized total before ordering.</p></noscript>
    <section class="section-form space-y-5" aria-labelledby="choose-packages">
        <h2 id="choose-packages" class="font-semibold text-cocoa-600">Choose and customize packages</h2>
        <p class="text-sm">Each layer option has a fixed price and listed inclusions. Themes, design requests and reference photos do not change that price.</p>
        <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
            @forelse ($products as $product)
                <article class="catalog-card space-y-3">
                    @if ($product->photo_path)<img src="{{ asset('storage/'.$product->photo_path) }}" alt="{{ $product->product_name }}" width="360" height="480" loading="lazy" class="catalog-card-photo">@endif
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
                <input type="hidden" :name="'items['+index+'][draft_key]'" :value="item.draft_key">
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
                @unless ($staff)
                    <div><label :for="'images-'+item.uid">Design reference photos for this package (optional)</label><input :id="'images-'+item.uid" :name="'items['+index+'][images][]'" type="file" multiple accept="image/jpeg,image/png,image/webp"><p class="text-sm mt-1">Up to 5 JPG, PNG or WebP images, 5 MB each. These are design references, not payment receipts.</p></div>
                    <template x-for="saved in (item.staged_images || [])" :key="saved.staged_path"><label class="flex items-center gap-2 text-sm text-cocoa-600"><input type="checkbox" :name="'items['+index+'][remove_staged_images][]'" :value="saved.staged_path"><span x-text="'Remove saved reference: ' + saved.original_filename"></span></label></template>
                @endunless
                <p class="font-semibold text-right" x-text="'Package line total: ' + money(lineTotal(item))"></p>
            </section>
        </template>
    </section>


    <div class="section-form flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <p role="alert" x-show="error" x-text="error" class="text-red-800" x-cloak></p>
        <button type="submit" class="px-6 py-3 bg-cocoa-600 text-white rounded-lg hover:bg-cocoa-700">Continue to contact and pickup</button>
    </div>
</form>
