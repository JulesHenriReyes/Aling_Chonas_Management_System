@props(['products', 'context', 'packageLinks' => []])
    <section id="all-packages" class="store-catalog" aria-label="Cake packages">
        @forelse($products as $product)
            <article class="store-package">
                @if($product->photo_path)
                    <img class="store-package-photo" src="{{ asset('storage/'.$product->photo_path) }}" alt="{{ $product->product_name }}" width="400" height="400" loading="{{ $loop->index < 2 ? 'eager' : 'lazy' }}">
                @else
                    <div class="store-package-photo photo-placeholder">
                        <x-icon name="cake" />
                        <span>{{ $product->product_name }}</span>
                    </div>
                @endif
                <div class="store-package-body">
                    <h2>{{ $product->product_name }}</h2>
                    @if($product->description)
                        <p>{{ $product->description }}</p>
                    @endif
                    <strong class="store-package-price">@if($product->options->isNotEmpty()) From ₱{{ number_format($product->options->min('price'), 2) }} @else Price on request @endif</strong>
                    <a class="ui-button primary" href="{{ $packageLinks[$product->id] ?? route($context['customize_route'], ['product' => $product, 'line' => (string) Str::uuid()]) }}" aria-label="Select {{ $product->product_name }} package">Select package</a>
                </div>
            </article>
        @empty
            <p class="empty-state">Packages are being updated. Please check back or contact the bakery.</p>
        @endforelse
    </section>
