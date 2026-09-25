@php($key = $option->id ?? 'new')
@php($restore = old('_option') === (string) $key)
<form action="{{ $option->exists ? route('options.update', [$product, $option]) : route('options.store', $product) }}" method="POST" data-validation-active="{{ $restore ? 'true' : 'false' }}" class="border-t border-cocoa-100 pt-5 space-y-4">
    @csrf @if ($option->exists) @method('PATCH') @endif
    <input type="hidden" name="_option" value="{{ $key }}">
    <h3 class="font-semibold">{{ $option->exists ? $option->layers.'-layer option' : 'Add a layer option' }}</h3>
    <div class="grid sm:grid-cols-2 gap-4">
        <div><label for="layers-{{ $key }}">Available layer count</label><input id="layers-{{ $key }}" name="layers" type="number" min="1" max="10" required value="{{ $restore ? old('layers') : $option->layers }}" class="w-full"></div>
        <div><label for="price-{{ $key }}">Fixed package price (₱)</label><input id="price-{{ $key }}" name="price" type="number" min="0.02" max="999999.98" step="0.02" required value="{{ $restore ? old('price') : $option->price }}" class="w-full"></div>
    </div>
    <div><label for="contents-{{ $key }}">Everything included in this price</label><textarea id="contents-{{ $key }}" name="included_contents" rows="3" maxlength="2000" required class="w-full" placeholder="Example: 2-layer cake and 8 cupcakes…">{{ $restore ? old('included_contents') : $option->included_contents }}</textarea></div>
    <input type="hidden" name="is_active" value="0">
    <label class="flex items-center gap-3"><input type="checkbox" name="is_active" value="1" @checked($restore ? old('is_active') : $option->is_active)>Option available</label>
    <button class="px-4 py-2 border border-cocoa-200 rounded-lg hover:bg-cream-100">{{ $option->exists ? 'Save layer option' : 'Add layer option' }}</button>
</form>
