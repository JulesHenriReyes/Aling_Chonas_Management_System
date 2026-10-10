@php($key = $option->id ?? 'new')
@php($restore = old('_option') === (string) $key)
@php($rows = $restore ? old('included_items', []) : $option->includedItems->map(fn ($item) => ['add_on_id' => $item->id, 'quantity' => $item->pivot->quantity])->all())
<div class="catalog-section">
    {{-- Unified Card Header: Title + Status Pill + Availability Action --}}
    <div class="flex flex-wrap items-center justify-between gap-3 pb-3 mb-4 border-b border-cocoa-100">
        <div class="flex items-center gap-2.5">
            <h3 class="font-bold text-cocoa-800 text-sm tracking-tight">
                {{ $option->exists ? $option->layers . '-layer option' : 'Add a layer option' }}
            </h3>
            @if ($option->exists)
                <span data-option-availability class="text-xs px-2.5 py-0.5 rounded-full font-medium {{ $option->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                    {{ $option->is_active ? 'Available' : 'Unavailable' }}
                </span>
            @endif
        </div>
        @if ($option->exists)
            <form data-catalog-save data-catalog-toggle action="{{ route('options.toggle', [$product, $option]) }}" method="POST" class="inline">
                @csrf @method('PATCH')
                <button type="submit" class="catalog-toggle-btn {{ $option->is_active ? 'catalog-toggle-disable' : 'catalog-toggle-enable' }}">
                    {{ $option->is_active ? 'Make layer unavailable' : 'Make layer available' }}
                </button>
            </form>
        @endif
    </div>

    {{-- Main Layer Form: Tier Count, Pricing, and Inclusions --}}
    <form data-catalog-save action="{{ $option->exists ? route('options.update', [$product, $option]) : route('options.store', $product) }}" method="POST" data-validation-active="{{ $restore ? 'true' : 'false' }}" class="space-y-4" x-data="packageInclusions({{ Js::from($inclusionItems->map->only(['id', 'name', 'description'])) }}, {{ Js::from($rows) }})">
        @csrf @if ($option->exists) @method('PATCH') @endif
        <input type="hidden" name="_option" value="{{ $key }}">

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label for="layers-{{ $key }}" class="block text-xs font-bold text-cocoa-700 mb-1">Available layer count</label>
                <input id="layers-{{ $key }}" name="layers" type="number" min="1" max="10" required value="{{ $restore ? old('layers') : $option->layers }}" class="w-full text-xs rounded-lg border-cocoa-200 bg-white font-semibold text-cocoa-800 focus:border-cocoa-400 focus:ring-cocoa-300">
            </div>
            <div>
                <label for="price-{{ $key }}" class="block text-xs font-bold text-cocoa-700 mb-1">Fixed package price (₱)</label>
                <input id="price-{{ $key }}" name="price" type="number" min="0.02" max="999999.98" step="0.02" required value="{{ $restore ? old('price') : $option->price }}" class="w-full text-xs rounded-lg border-cocoa-200 bg-white font-semibold text-cocoa-800 focus:border-cocoa-400 focus:ring-cocoa-300">
            </div>
        </div>

        <fieldset class="space-y-3 pt-1">
            <div class="flex items-center justify-between">
                <legend class="font-bold text-cocoa-700 text-xs uppercase tracking-wider">Included per package (Free)</legend>
                <span class="text-[11px] text-cocoa-400">Adds ₱0 to fixed price</span>
            </div>
            <p class="text-xs text-cocoa-500">Quantity counts catalog units bundled in this package (e.g. candles, boxes of cupcakes).</p>

            <div class="space-y-2.5">
                <template x-for="(row, index) in rows" :key="row.uid">
                    <div class="inclusion-editor-row p-3 bg-cream-50/70 border border-cocoa-200/80 rounded-xl space-y-2">
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-start">
                            <div class="sm:col-span-7">
                                <label :for="'included-item-{{ $key }}-'+row.uid" class="block text-xs font-semibold text-cocoa-700 mb-1">Catalog item</label>
                                <select :id="'included-item-{{ $key }}-'+row.uid" :name="'included_items['+index+'][add_on_id]'" x-model="row.add_on_id" required class="w-full text-xs rounded-lg border-cocoa-200 bg-white focus:border-cocoa-400 focus:ring-cocoa-300">
                                    <option value="">Choose an item</option>
                                    <template x-for="entry in catalog" :key="entry.id">
                                        <option :value="entry.id" :selected="String(entry.id) === String(row.add_on_id)" :disabled="used(entry.id, row)" x-text="entry.name"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="sm:col-span-3">
                                <label :for="'included-quantity-{{ $key }}-'+row.uid" class="block text-xs font-semibold text-cocoa-700 mb-1">Quantity per package</label>
                                <input :id="'included-quantity-{{ $key }}-'+row.uid" :name="'included_items['+index+'][quantity]'" type="number" min="1" max="999" step="1" x-model="row.quantity" required class="w-full text-xs rounded-lg border-cocoa-200 bg-white font-semibold text-cocoa-800 text-center focus:border-cocoa-400 focus:ring-cocoa-300">
                            </div>
                            <div class="sm:col-span-2 flex sm:justify-end pt-1 sm:pt-6">
                                <button type="button" @click="rows.splice(index, 1)" :aria-label="'Remove included item ' + (index + 1)" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-red-600 hover:text-red-700 hover:bg-red-50 border border-transparent hover:border-red-200 rounded-lg transition">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    <span>Remove</span>
                                </button>
                            </div>
                        </div>
                        <p class="text-xs text-cocoa-500 italic pl-0.5" x-show="row.add_on_id" x-text="catalog.find(entry => String(entry.id) === String(row.add_on_id))?.description || ''"></p>
                    </div>
                </template>
            </div>

            <p x-show="!rows.length" class="text-xs text-cocoa-500 italic py-1">No reusable items included in this option yet.</p>

            <div>
                <button type="button" @click="add()" :disabled="rows.length >= catalog.length || rows.length >= 50" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-cocoa-300 hover:border-cocoa-400 text-cocoa-700 hover:bg-cream-100 rounded-lg text-xs font-semibold transition shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Add included item</span>
                </button>
            </div>
            @if ($inclusionItems->isEmpty())
                <p class="text-xs text-cocoa-500"><a href="{{ route('add-ons.create') }}" class="underline hover:text-cocoa-700">Create a catalog item</a> first, then return to include it here.</p>
            @endif
        </fieldset>

        <input type="hidden" name="included_contents" value="">

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="px-4 py-2 bg-cocoa-600 hover:bg-cocoa-700 text-white font-semibold text-xs rounded-lg transition shadow-sm">
                {{ $option->exists ? 'Save layer option' : 'Add layer option' }}
            </button>
            @if (!$option->exists && $product->options->isNotEmpty())
                <button type="button" @click="$dispatch('close-add-option')" class="px-4 py-2 border border-cocoa-200 hover:bg-cream-100 text-xs font-semibold text-cocoa-600 rounded-lg transition">
                    Cancel
                </button>
            @endif
        </div>
    </form>
</div>
