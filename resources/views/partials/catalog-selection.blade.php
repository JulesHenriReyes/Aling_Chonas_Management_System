<script src="{{ asset('js/catalog-order.js') }}?v={{ filemtime(public_path('js/catalog-order.js')) }}"></script>
<form action="{{ $staff ? route('orders.continue') : route('public.order.continue') }}" method="POST" enctype="multipart/form-data"
    x-data="catalogOrder({{ Js::from($products) }}, {{ Js::from(old('items', $draft['items'] ?? [])) }}, {{ Js::from(route('public.order.quote')) }}, {{ $staff ? 'true' : 'false' }})" @submit="if (!items.length) { error = 'Add at least one cake package.'; $event.preventDefault() }" class="catalog-selection-form space-y-6">
    @csrf
    <noscript><p>Enable JavaScript to select packages and review your itemized total before ordering.</p></noscript>
    <section class="section-form space-y-5" aria-labelledby="choose-packages">
        <h2 id="choose-packages" class="font-semibold text-cocoa-600">Choose and customize packages</h2>
        <p class="text-sm">Each layer option has a fixed price and listed inclusions. Themes, design requests and reference photos do not change that price.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-5">
            @forelse ($products as $product)
                <article class="catalog-card space-y-3">
                    @if ($product->photo_path)
                        <img src="{{ asset('storage/'.$product->photo_path) }}" alt="{{ $product->product_name }}" width="360" height="270" loading="lazy" class="catalog-card-photo w-full aspect-[4/3] object-cover rounded-lg border border-cocoa-100 block">
                    @else
                        <div class="w-full aspect-[4/3] rounded-lg border border-cocoa-100 bg-cream-50 flex flex-col items-center justify-center text-cocoa-400 gap-2">
                            <x-icon name="cake" class="w-10 h-10 text-cocoa-400" />
                            <span class="text-xs font-medium text-cocoa-500">{{ $product->product_name }}</span>
                        </div>
                    @endif
                    <h3 class="font-semibold text-cocoa-600">{{ $product->product_name }}</h3>
                    @if ($product->description)<p class="text-sm">{{ $product->description }}</p>@endif
                    @if ($product->options->count() > 1)
                        <p class="text-sm">From ₱{{ number_format($product->options->min('price'), 2) }} · {{ $product->options->pluck('layers')->join(', ') }} layer options available</p>
                    @elseif ($product->options->isNotEmpty())
                        <p class="text-sm">₱{{ number_format($product->options->first()->price, 2) }} · {{ $product->options->first()->layers }} layer(s)</p>
                    @else
                        <p class="text-sm text-cocoa-400">Price on request</p>
                    @endif
                    <button type="button" @click="add(products.find(product => product.id === {{ $product->id }}))" class="px-4 py-2 border border-cocoa-200 rounded-lg hover:bg-cream-100" aria-label="Add {{ $product->product_name }}">Add package</button>
                </article>
            @empty
                <p class="sm:col-span-2">Cake packages are being updated. Please contact the bakery or check back once layer options and prices are available.</p>
            @endforelse
        </div>
        <template x-for="(item, index) in items" :key="item.uid">
            @include('partials.package-line-fields')
        </template>
    </section>


    <div class="section-form flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <p role="alert" x-show="error" x-text="error" class="text-red-800" x-cloak></p>
        <button type="submit" class="px-6 py-3 bg-cocoa-600 text-white rounded-lg hover:bg-cocoa-700">Continue to contact and pickup</button>
    </div>
</form>

