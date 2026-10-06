            <section class="item-section space-y-4" :aria-label="'Selected package ' + (index + 1)">
                <div class="page-heading">
                    <div class="flex items-center gap-3.5 min-w-0">
                        <template x-if="product(item)?.photo_path">
                            <img :src="'/storage/' + product(item).photo_path" :alt="product(item)?.product_name" width="64" height="64" loading="lazy" class="w-14 h-14 sm:w-16 sm:h-16 object-cover rounded-lg border border-cocoa-100 shrink-0 bg-cream-100 shadow-sm">
                        </template>
                        <h3 class="font-semibold text-cocoa-600 text-base sm:text-lg" x-text="product(item)?.product_name || 'Unavailable package'"></h3>
                    </div>
                    @unless($customizing ?? false)<button type="button" @click="items.splice(index, 1)" class="underline px-3 py-2 text-sm text-cocoa-400 hover:text-red-700 transition" :aria-label="'Remove package ' + (index + 1)">Remove</button>@endunless
                </div>
                <input type="hidden" :name="'items['+index+'][product_id]'" :value="item.product_id">
                <input type="hidden" :name="'items['+index+'][draft_key]'" :value="item.draft_key">
                <div class="grid sm:grid-cols-[2fr_1fr] gap-4">
                    <template x-if="(product(item)?.options || []).length > 1">
                        <div><label :for="'option-'+item.uid">Layer option and fixed price</label>
                            <select :id="'option-'+item.uid" :name="'items['+index+'][package_option_id]'" x-model="item.package_option_id" required class="w-full">
                                <option value="">Choose an option</option><template x-for="option in (product(item)?.options || [])" :key="option.id"><option :value="option.id" :selected="String(option.id) === String(item.package_option_id)" x-text="option.layers + ' layer(s) · ' + money(option.price)"></option></template>
                            </select></div>
                    </template>
                    <template x-if="(product(item)?.options || []).length === 1">
                        <div>
                            <label>Cake size & fixed price</label>
                            <input type="hidden" :name="'items['+index+'][package_option_id]'" :value="item.package_option_id">
                            <div class="px-3.5 py-2.5 bg-cream-50 border border-cocoa-200 rounded-lg text-cocoa-800 font-semibold text-sm" x-text="(option(item)?.layers || product(item)?.options[0]?.layers) + ' layer(s) · ' + money(option(item)?.price || product(item)?.options[0]?.price)"></div>
                        </div>
                    </template>
                    <div><label :for="'quantity-'+item.uid">Packages</label><input :id="'quantity-'+item.uid" :name="'items['+index+'][quantity]'" type="number" min="1" max="999" step="1" x-model="item.quantity" required class="w-full"></div>
                </div>
                <div class="included-items text-sm space-y-2" style="grid-column: 1 / -1;">
                    <h4 class="font-semibold">Included per package · ₱0 extra</h4>
                    <p x-show="!option(item)">Choose an available layer option.</p>
                    <ul class="space-y-2">
                        <template x-for="included in (option(item)?.included_items || [])" :key="included.id">
                            <li><span x-text="included.name + ' × ' + included.pivot.quantity + ' per package'"></span>
                                <span class="block text-xs" x-show="Number(item.quantity) > 1" x-text="(included.pivot.quantity * Number(item.quantity || 0)) + ' across ' + item.quantity + ' packages'"></span>
                                <span class="block text-xs" x-text="included.description"></span>
                            </li>
                        </template>
                    </ul>
                    <p class="whitespace-pre-line" x-show="option(item)?.included_contents" x-text="option(item)?.included_contents"></p>
                </div>
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
                                <label :for="'extra-'+item.uid+'-'+extra.id" x-text="extra.name+' quantity for this whole order line'"></label>
                                <input :id="'extra-'+item.uid+'-'+extra.id" :name="'items['+index+'][add_ons]['+item.add_ons.indexOf(selectedExtra(item, extra.id))+'][quantity]'" type="number" min="1" max="999" step="1" x-model="selectedExtra(item, extra.id).quantity" required class="w-full sm:max-w-40">
                            </div></template>
                        </div>
                    </template>
                    <p x-show="!(product(item)?.add_ons.length)" class="text-sm">No paid extras for this package.</p>
                </fieldset>

                <div class="col-span-full border-t border-cocoa-200 pt-4 space-y-1" style="grid-column: 1 / -1;">
                    <h4 class="font-semibold text-cocoa-600 text-sm">Cake design & special instructions (optional)</h4>
                    <p class="text-xs text-cocoa-500">Theme colors, special requests, and reference photos apply specifically to your cake, not to paid extras.</p>
                </div>

                <div><label :for="'theme-'+item.uid">Theme and colors (optional)</label><input :id="'theme-'+item.uid" :name="'items['+index+'][themes]'" maxlength="255" x-model="item.themes" class="w-full"></div>
                <div><label :for="'request-'+item.uid">Design instructions and special requests (optional)</label><textarea :id="'request-'+item.uid" :name="'items['+index+'][special_request]'" rows="2" maxlength="1000" x-model="item.special_request" class="w-full"></textarea></div>
                @unless ($staff)
                    <div style="grid-column: 1 / -1;" class="col-span-full"><label :for="'images-'+item.uid" class="inline-flex items-center gap-1">Design reference photos for this package (optional) <x-tooltip text="Attach cake inspiration or design sketches (up to 5 photos, 5 MB each). These guide our cake decorators and are not payment receipts." /></label><input :id="'images-'+item.uid" :name="'items['+index+'][images][]'" type="file" multiple accept="image/jpeg,image/png,image/webp"><p class="text-sm mt-1">Up to 5 JPG, PNG or WebP images, 5 MB each. These are design references, not payment receipts.</p></div>
                    <template x-for="saved in (item.staged_images || [])" :key="saved.staged_path"><label class="flex items-center gap-2 text-sm text-cocoa-600"><input type="checkbox" :name="'items['+index+'][remove_staged_images][]'" :value="saved.staged_path"><span x-text="'Remove saved reference: ' + saved.original_filename"></span></label></template>
                @endunless

                <div class="col-span-full border-t border-cocoa-100 pt-3 flex justify-end" style="grid-column: 1 / -1;">
                    <p class="font-semibold text-right text-base text-cocoa-600" x-text="'Package line total: ' + money(lineTotal(item))"></p>
                </div>
            </section>

