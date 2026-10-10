@props(['staff' => false, 'customizing' => false])
            <section class="item-section space-y-4" :aria-label="'Selected package ' + (index + 1)">
                <div class="page-heading">
                    <div class="flex items-center gap-3.5 min-w-0 max-w-full">
                        <template x-if="product(item)?.photo_path">
                            <img :src="'/storage/' + product(item).photo_path" :alt="product(item)?.product_name" width="64" height="64" loading="lazy" class="w-14 h-14 sm:w-16 sm:h-16 object-cover rounded-lg border border-cocoa-100 shrink-0 bg-cream-100 shadow-sm">
                        </template>
                        <h3 class="min-w-0 font-semibold text-cocoa-600 text-base sm:text-lg" x-text="product(item)?.product_name || 'Unavailable package'"></h3>
                    </div>
                    @unless($customizing ?? false)<button type="button" @click="items.splice(index, 1)" class="underline px-3 py-2 text-sm text-cocoa-400 hover:text-red-700 transition" :aria-label="'Remove package ' + (index + 1)">Remove</button>@endunless
                </div>
                <input type="hidden" :name="'items['+index+'][product_id]'" :value="item.product_id">
                <input type="hidden" :name="'items['+index+'][draft_key]'" :value="item.draft_key">
                <div class="grid sm:grid-cols-[1fr_auto] gap-4 items-end {{ !$staff ? 'customer-size-quantity' : '' }}">
                    <div>
                        <label class="block text-xs font-semibold text-cocoa-700 uppercase tracking-wider mb-1.5">
                            Cake size & fixed price
                        </label>
                        <template x-if="(product(item)?.options || []).length > 1">
                            <div>
                                <label :for="'option-'+item.uid" class="sr-only">Layer option and fixed price</label>
                                <select :id="'option-'+item.uid" :name="'items['+index+'][package_option_id]'" x-model="item.package_option_id" required class="w-full">
                                    <option value="">Choose an option</option>
                                    <template x-for="option in (product(item)?.options || [])" :key="option.id">
                                        <option :value="option.id" :selected="String(option.id) === String(item.package_option_id)" x-text="option.layers + ' layer(s) · ' + money(option.price)"></option>
                                    </template>
                                </select>
                            </div>
                        </template>
                        <template x-if="(product(item)?.options || []).length === 1">
                            <div>
                                <input type="hidden" :name="'items['+index+'][package_option_id]'" :value="item.package_option_id">
                                <div class="px-3.5 py-2.5 bg-cream-50 border border-cocoa-200 rounded-lg text-cocoa-800 font-semibold text-sm flex items-center justify-between">
                                    <span x-text="(option(item)?.layers || product(item)?.options[0]?.layers) + ' layer(s)'"></span>
                                    <span class="text-cocoa-700 font-bold" x-text="money(option(item)?.price || product(item)?.options[0]?.price)"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div>
                        <label :for="'quantity-'+item.uid" class="block text-xs font-semibold text-cocoa-700 uppercase tracking-wider mb-1.5">
                            Quantity
                        </label>
                        <div class="package-quantity-stepper inline-flex items-center rounded-xl border border-cocoa-200/90 bg-white shadow-2xs overflow-hidden h-[42px]">
                            <button type="button"
                                    @click="item.quantity = Math.max(1, (Number(item.quantity) || 1) - 1)"
                                    class="w-10 h-full flex items-center justify-center text-cocoa-500 hover:bg-cream-100 hover:text-cocoa-800 active:bg-cream-200 transition font-bold text-base cursor-pointer focus:outline-none"
                                    aria-label="Decrease quantity">
                                −
                            </button>
                            <input :id="'quantity-'+item.uid"
                                   :name="'items['+index+'][quantity]'"
                                   type="number"
                                   min="1"
                                   max="99"
                                   step="1"
                                   x-model.number="item.quantity"
                                   class="w-12 h-full text-center text-sm font-bold text-cocoa-800 border-x border-cocoa-100 bg-transparent focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                   required>
                            <button type="button"
                                    @click="item.quantity = Math.min(99, (Number(item.quantity) || 1) + 1)"
                                    class="w-10 h-full flex items-center justify-center text-cocoa-500 hover:bg-cream-100 hover:text-cocoa-800 active:bg-cream-200 transition font-bold text-base cursor-pointer focus:outline-none"
                                    aria-label="Increase quantity">
                                +
                            </button>
                        </div>
                    </div>
                </div>
                <div class="included-items text-sm {{ $staff ? 'space-y-2' : 'customer-bundle-reward' }}" @unless($staff) :class="{ 'has-free-items': (option(item)?.included_items || []).length > 0 }" @endunless style="grid-column: 1 / -1;">
                    @unless($staff)<x-icon name="check" class="bundle-reward-icon" x-show="(option(item)?.included_items || []).length > 0" />@endunless
                    <h4 class="font-semibold text-cocoa-700">{{ $staff ? 'Included per package (Free)' : 'Included with your package' }}@unless($staff)<span class="bundle-free-label" x-show="(option(item)?.included_items || []).length > 0">Free</span>@endunless</h4>
                    <p x-show="!option(item)">Choose an available layer option.</p>
                    <ul class="space-y-1.5">
                        <template x-for="included in (option(item)?.included_items || [])" :key="included.id">
                            <li><span class="font-medium text-cocoa-700" x-text="included.name + ' × ' + included.pivot.quantity + ' per package'"></span>
                                <span class="block text-xs text-cocoa-400" x-show="Number(item.quantity) > 1" x-text="(included.pivot.quantity * Number(item.quantity || 0)) + ' across ' + item.quantity + ' packages'"></span>
                            </li>
                        </template>
                    </ul>
                    <p class="whitespace-pre-line text-xs text-cocoa-500" x-show="option(item)?.included_contents" x-text="option(item)?.included_contents"></p>
                </div>
                @unless($staff)
                    @include('components.ordering.visual-extras')
                @else
                <fieldset class="space-y-3"><legend class="font-semibold mb-2">Paid extras (optional)</legend>
                    <template x-for="extra in (product(item)?.add_ons || [])" :key="extra.id">
                        <div class="border-t border-cocoa-100 pt-3">
                            <div class="flex gap-3 items-start">
                                <template x-if="extra.photo_path"><img :src="'/storage/'+extra.photo_path" :alt="extra.name" width="80" height="80" loading="lazy" class="w-20 h-20 object-cover rounded-lg shrink-0"></template>
                                <div class="flex-1 min-w-0">
                                    <label class="flex items-start gap-3 cursor-pointer"><input type="checkbox" :checked="!!selectedExtra(item, extra.id)" @change="toggleExtra(item, extra, $event.target.checked)" class="mt-1 shrink-0"><span class="min-w-0"><strong class="text-cocoa-700 block" x-text="extra.name + ' · ' + money(extra.price)"></strong></span></label>
                                    <template x-if="selectedExtra(item, extra.id)">
                                        <div class="extra-quantity-control">
                                            <input type="hidden" :name="'items['+index+'][add_ons]['+item.add_ons.indexOf(selectedExtra(item, extra.id))+'][add_on_id]'" :value="extra.id">
                                            <label :for="'extra-'+item.uid+'-'+extra.id" class="sr-only" x-text="extra.name + ' quantity for this package line'"></label>
                                            <span class="extra-quantity-label">Quantity:</span>
                                            <div class="extra-stepper">
                                                <button type="button" class="extra-stepper-btn" @click="decreaseExtra(item, extra)" :aria-label="'Decrease ' + extra.name + ' quantity'">−</button>
                                                <input :id="'extra-'+item.uid+'-'+extra.id"
                                                       :name="'items['+index+'][add_ons]['+item.add_ons.indexOf(selectedExtra(item, extra.id))+'][quantity]'"
                                                       type="number"
                                                       min="1"
                                                       max="999"
                                                       step="1"
                                                       x-model.number="selectedExtra(item, extra.id).quantity"
                                                       @input="if ($event.target.value < 1 && $event.target.value !== '') $event.target.value = 1"
                                                       @change="setExtraQuantity(item, extra, $event.target.value)"
                                                       @blur="setExtraQuantity(item, extra, $event.target.value)"
                                                       required
                                                       class="extra-stepper-input"
                                                       :aria-label="extra.name + ' quantity'">
                                                <button type="button" class="extra-stepper-btn" @click="increaseExtra(item, extra)" :aria-label="'Increase ' + extra.name + ' quantity'">+</button>
                                            </div>
                                            <span class="extra-stepper-subtotal" x-text="money(Number(selectedExtra(item, extra.id).quantity || 0) * extra.price)"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                    <p x-show="!(product(item)?.add_ons.length)" class="text-sm">No paid extras for this package.</p>
                </fieldset>
                @endunless

                <div class="col-span-full border-t border-cocoa-200 pt-4 space-y-1" style="grid-column: 1 / -1;">
                    <h4 class="font-semibold text-cocoa-600 text-sm">Cake design & special instructions (optional)</h4>
                    <p class="text-xs text-cocoa-500">Theme colors, special requests, and reference photos apply specifically to your cake, not to paid extras.</p>
                </div>

                <div class="design-fields {{ $staff ? 'staff-design-fields col-span-full' : '' }}" @if($staff) style="grid-column: 1 / -1;" @endif>
                    <div class="{{ $staff ? 'staff-design-field' : '' }}"><label :for="'theme-'+item.uid">Theme and colors (optional)</label><input :id="'theme-'+item.uid" :name="'items['+index+'][themes]'" maxlength="255" x-model="item.themes" class="w-full"></div>
                    <div class="{{ $staff ? 'staff-design-field' : '' }}"><label :for="'request-'+item.uid">Design instructions and special requests (optional)</label><textarea :id="'request-'+item.uid" :name="'items['+index+'][special_request]'" rows="2" maxlength="1000" x-model="item.special_request" class="w-full"></textarea></div>
                </div>
                    <div style="grid-column: 1 / -1;" class="col-span-full">
                        <label :for="'images-'+item.uid" class="inline-flex items-center gap-1 font-medium text-cocoa-700">Design reference photos for this package (optional) <x-tooltip text="Attach cake inspiration or design sketches (up to 5 photos, 5 MB each). These guide our cake decorators and are not payment receipts." /></label>
                        @unless($staff)
                        <div class="customer-reference-drop" x-data="{ dragging: false }" :aria-busy="uploading" :class="{ 'is-dragging': dragging, 'is-uploading': uploading }" @dragover.prevent="dragging = !uploading" @dragleave="if (!$el.contains($event.relatedTarget)) dragging = false" @drop.prevent="dragging = false; acceptReferenceDrop(item, $event)">
                            <x-icon name="plus" class="reference-upload-icon" />
                            <strong>Have an inspiration photo? Upload your cake design reference here.</strong>
                            <p>Drag photos here or browse from your device.</p>
                            <input class="reference-file-input" :id="'images-'+item.uid" :name="'items['+index+'][images][]'" type="file" multiple accept="image/jpeg,image/png,image/webp" :disabled="uploading" :aria-describedby="'reference-help-'+item.uid">
                            <p role="status" class="reference-upload-status" x-text="uploading ? 'Saving reference photos…' : uploadStatus"></p>
                        </div>
                        @else
                        <input :id="'images-'+item.uid" :name="'items['+index+'][images][]'" type="file" multiple accept="image/jpeg,image/png,image/webp">
                        @endunless
                        <p class="field-error" role="alert" x-show="fieldErrors['items.'+index+'.images'] || fieldErrors['items.'+index+'.images.0']" x-text="(fieldErrors['items.'+index+'.images'] || fieldErrors['items.'+index+'.images.0'] || [])[0]" x-cloak></p>
                        <p :id="'reference-help-'+item.uid" class="text-sm mt-1 text-cocoa-500">Up to 5 JPG, PNG or WebP images, 5 MB each. These are design references, not payment receipts.</p>

                        <div class="space-y-2 mt-2.5" x-show="(item.staged_images || []).length > 0">
                            <template x-for="saved in (item.staged_images || [])" :key="saved.staged_path">
                                <div class="reference-card flex items-center justify-between gap-3 p-2.5 bg-cream-50/80 hover:bg-cream-100/70 rounded-xl border border-cocoa-200/80 transition-colors">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <!-- Thumbnail preview -->
                                        <div class="relative w-12 h-12 rounded-lg overflow-hidden bg-white border border-cocoa-200 shrink-0 flex items-center justify-center shadow-xs">
                                            <template x-if="saved.staged_path">
                                                <img :src="saved.preview_url || '/storage/' + saved.staged_path"
                                                     :alt="saved.original_filename"
                                                     class="w-full h-full object-cover"
                                                     x-on:error="$el.classList.add('hidden'); if ($el.nextElementSibling) $el.nextElementSibling.classList.remove('hidden')">
                                            </template>
                                            <div class="hidden flex flex-col items-center justify-center w-full h-full text-cocoa-400 bg-cream-50">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            </div>
                                        </div>

                                        <!-- Filename & indicator badge -->
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-cocoa-800 truncate" :title="saved.original_filename" x-text="saved.original_filename"></p>
                                            <span class="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-700 bg-emerald-50 px-2 py-0.25 rounded-md border border-emerald-200/80 mt-0.5">
                                                <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                                Saved reference
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Hidden checkbox preserving form compatibility -->
                                    <input type="checkbox"
                                           class="sr-only"
                                           :name="'items['+index+'][remove_staged_images][]'"
                                           :value="saved.staged_path">

                                    <!-- Styled action button -->
                                    <button type="button"
                                            :disabled="uploading"
                                            @click="const card = $el.closest('.reference-card'); const cb = card?.querySelector('input[type=checkbox]'); if (cb) { cb.checked = true; cb.dispatchEvent(new Event('change', { bubbles: true })); }"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold text-rose-700 hover:text-rose-800 bg-white hover:bg-rose-50 active:bg-rose-100 border border-rose-200 hover:border-rose-300 rounded-lg shadow-xs transition-colors shrink-0 cursor-pointer focus:outline-none focus:ring-2 focus:ring-rose-400/20"
                                            :class="uploading ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'"
                                            :title="'Remove ' + saved.original_filename">
                                        <svg class="w-3.5 h-3.5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                        <span>Remove</span>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                <div class="col-span-full border-t border-cocoa-100 pt-3 flex justify-end" style="grid-column: 1 / -1;">
                    <p class="font-semibold text-right text-base text-cocoa-600" x-text="'Package line total: ' + money(lineTotal(item))"></p>
                </div>
            </section>
