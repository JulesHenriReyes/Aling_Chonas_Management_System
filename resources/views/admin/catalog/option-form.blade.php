@php($key = $option->id ?? 'new')
@php($restore = old('_option') === (string) $key)
@php($rows = $restore ? old('included_items', []) : $option->includedItems->map(fn ($item) => ['add_on_id' => $item->id, 'quantity' => $item->pivot->quantity])->all())
<form action="{{ $option->exists ? route('options.update', [$product, $option]) : route('options.store', $product) }}" method="POST" data-validation-active="{{ $restore ? 'true' : 'false' }}" class="border-t border-cocoa-100 pt-5 space-y-4" x-data="packageInclusions({{ Js::from($inclusionItems->map->only(['id', 'name', 'description'])) }}, {{ Js::from($rows) }})">
    @csrf @if ($option->exists) @method('PATCH') @endif
    <input type="hidden" name="_option" value="{{ $key }}">
    <div class="flex items-center justify-between">
        <h3 class="font-semibold text-cocoa-800">{{ $option->exists ? $option->layers.'-layer option' : 'Add a layer option' }}</h3>
        @if ($option->exists)
            <span class="text-xs px-2.5 py-0.5 rounded-full font-medium {{ $option->is_active ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                {{ $option->is_active ? 'Available' : 'Unavailable' }}
            </span>
        @endif
    </div>
    <div class="grid sm:grid-cols-2 gap-4">
        <div><label for="layers-{{ $key }}">Available layer count</label><input id="layers-{{ $key }}" name="layers" type="number" min="1" max="10" required value="{{ $restore ? old('layers') : $option->layers }}" class="w-full"></div>
        <div><label for="price-{{ $key }}">Fixed package price (₱)</label><input id="price-{{ $key }}" name="price" type="number" min="0.02" max="999999.98" step="0.02" required value="{{ $restore ? old('price') : $option->price }}" class="w-full"></div>
    </div>
    <fieldset class="space-y-3">
        <legend class="font-semibold text-cocoa-700">Included per package (Free)</legend>
        <p class="text-sm">Quantity counts catalog units in one package. For example, 2 of an item named “Box of 6 cupcakes” means two boxes per package.</p>
        <template x-for="(row, index) in rows" :key="row.uid">
            <div class="inclusion-editor-row">
                <div><label :for="'included-item-{{ $key }}-'+row.uid">Catalog item</label>
                    <select :id="'included-item-{{ $key }}-'+row.uid" :name="'included_items['+index+'][add_on_id]'" x-model="row.add_on_id" required class="w-full">
                        <option value="">Choose an item</option>
                        <template x-for="entry in catalog" :key="entry.id"><option :value="entry.id" :disabled="used(entry.id, row)" x-text="entry.name"></option></template>
                    </select>
                    <p class="text-sm mt-1" x-text="catalog.find(entry => String(entry.id) === String(row.add_on_id))?.description || ''"></p>
                </div>
                <div><label :for="'included-quantity-{{ $key }}-'+row.uid">Quantity per package</label><input :id="'included-quantity-{{ $key }}-'+row.uid" :name="'included_items['+index+'][quantity]'" type="number" min="1" max="999" step="1" x-model="row.quantity" required class="w-full"></div>
                <button type="button" @click="rows.splice(index, 1)" :aria-label="'Remove included item ' + (index + 1)" class="underline px-3 py-2">Remove</button>
            </div>
        </template>
        <p x-show="!rows.length" class="text-sm">No reusable items included yet.</p>
        <button type="button" @click="add()" :disabled="rows.length >= catalog.length || rows.length >= 50" class="px-4 py-2 border border-cocoa-200 rounded-lg disabled:opacity-50">Add included item</button>
        @if ($inclusionItems->isEmpty())<p class="text-sm"><a href="{{ route('add-ons.create') }}" class="underline">Create a catalog item</a> first, then return to include it here.</p>@endif
    </fieldset>
    <input type="hidden" name="included_contents" value="">
    <input type="hidden" name="is_active" value="0">
    <label class="flex items-center gap-3"><input type="checkbox" name="is_active" value="1" @checked($restore ? old('is_active') : $option->is_active)>Option available</label>
    <div class="flex items-center gap-3">
        <button class="px-4 py-2 bg-cocoa-600 text-white rounded-lg hover:bg-cocoa-700 font-medium transition">{{ $option->exists ? 'Save layer option' : 'Add layer option' }}</button>
        @if (!$option->exists && $product->options->isNotEmpty())
            <button type="button" @click="$dispatch('close-add-option')" class="px-4 py-2 border border-cocoa-200 rounded-lg hover:bg-cream-100 text-sm text-cocoa-600">Cancel</button>
        @endif
    </div>
</form>
