@props(['context', 'products', 'product', 'line', 'editor'])
    <form method="POST"
          action="{{ route($context['save_route'], ['product' => $product, 'line' => $line]) }}"
          enctype="multipart/form-data"
          data-package-editor
          data-order-context="{{ $context['staff'] ? 'staff' : 'public' }}"
          data-editor-url="{{ route($context['editor_route'], ['product' => $product, 'line' => $line]) }}"
          class="catalog-selection-form customize-form"
          x-data="catalogOrder({{ Js::from($products) }}, {{ Js::from([$editor]) }}, {{ Js::from($context['quote_url']) }}, {{ $context['staff'] ? 'true' : 'false' }}, {{ Js::from($context['browser_prefix'].'-'.$line) }}, {{ $product->is_active && $product->options->isNotEmpty() ? 'false' : 'true' }})"
          @submit="if (uploading || busy) { $event.preventDefault(); error='Wait for your reference photos to finish saving.'; } else { busy=true; }">
        @csrf
        @if($context['staff'])<input type="hidden" name="draft_id" value="{{ $context['draft_id'] }}">@endif
        @if($context['staff'] && (!$product->is_active || $product->options->isEmpty()))<p class="field-error" role="alert">This package is unavailable. Your customization and photos are kept. Return to packages to remove it or select a replacement.</p>@endif
        <noscript><p>Enable JavaScript to customize this package.</p></noscript>

        <div class="customize-main">
            <template x-for="(item, index) in items" :key="item.uid">
                <x-ordering.package-fields :staff="$context['staff']" :customizing="true" />
            </template>
        </div>

        <aside class="customize-summary" aria-label="Price summary">
            <h2>{{ $context['staff'] ? 'Package summary' : 'Your package' }}</h2>
            @if($product->photo_path)
                <img src="{{ asset('storage/'.$product->photo_path) }}" alt="{{ $product->product_name }}" class="customize-photo" width="400" height="400">
            @endif
            <p class="font-semibold text-cocoa-700">{{ $product->product_name }}</p>
            <dl>
                <div>
                    <dt>Package & extras</dt>
                    <dd x-text="money(total())"></dd>
                </div>
                <div>
                    <dt>50% deposit</dt>
                    <dd x-text="money(Math.round(total()*100/2)/100)"></dd>
                </div>
            </dl>
            <p class="form-hint">Fixed prices. Themes and design requests do not add a charge. Extra quantities apply to this whole package line.</p>
            <p role="status" x-text="uploading ? 'Saving reference photos…' : uploadStatus"></p>
            <p class="field-error" role="alert" x-show="error" x-text="error" x-cloak></p>

            <div class="mobile-sticky-actions flex flex-col gap-2 pt-2">
                <button class="ui-button primary w-full" :disabled="uploading || busy || unavailable" type="submit" x-text="busy ? 'Saving…' : 'Save package to order'">Save package to order</button>
                <a class="ui-button quiet text-center" href="{{ route($context['select_route']) }}">Back to packages</a>
            </div>
        </aside>
    </form>
