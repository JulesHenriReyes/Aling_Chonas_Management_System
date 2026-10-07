from pathlib import Path

root = Path(__file__).resolve().parents[4]

def edit(path, before, after):
    file = root / path
    content = file.read_text(encoding='utf-8')
    if before not in content:
        raise RuntimeError(f'Missing edit anchor in {path}: {before[:80]}')
    file.write_text(content.replace(before, after), encoding='utf-8')

def write(path, content):
    file = root / path
    file.parent.mkdir(parents=True, exist_ok=True)
    file.write_text(content, encoding='utf-8')

write('app/Services/PublicPackageDraftService.php', '''<?php

namespace App\\Services;

/** Backwards-compatible public entry point to the shared package workspace. */
class PublicPackageDraftService extends PackageDraftService {}
''')

edit('app/Services/OrderDraftService.php', "foreach ($draft['items'] ?? [] as &$item)", "foreach ($draft['items'] as &$item)")
edit('app/Services/OrderDraftService.php', "Storage::disk('local')->putFileAs(\n                            'order_drafts/'.$request->session()->getId(),", "Storage::disk($staff ? 'staff_drafts' : 'local')->putFileAs(\n                            $staff ? $previous['owner_id'].'/'.$previous['scope'].'/'.$previous['draft_id'].'/'.$item['draft_key'] : 'order_drafts/'.$request->session()->getId(),")
edit('app/Services/OrderDraftService.php', "$saved[] = ['staged_path' => $path, 'original_filename' => $file->getClientOriginalName()];", "$saved[] = ['staged_path' => $path, 'original_filename' => $file->getClientOriginalName()] + ($staff ? ['staged_disk' => 'staff_drafts', 'image_id' => (string) Str::uuid()] : []);")
edit('app/Services/OrderDraftService.php', "Storage::disk('local')->delete($newPaths);", "Storage::disk($staff ? 'staff_drafts' : 'local')->delete($newPaths);")
edit('app/Services/OrderDraftService.php', "Storage::disk('local')->delete($image['staged_path']);", "Storage::disk($image['staged_disk'] ?? 'local')->delete($image['staged_path']);")

index = (root/'resources/views/public/index.blade.php').read_text(encoding='utf-8')
catalog_start = index.index('    <section class="store-catalog"')
catalog_end = index.index('    </section>', catalog_start) + len('    </section>')
cards = index[catalog_start:catalog_end]
cards = cards.replace('class="store-catalog"', 'id="all-packages" class="store-catalog"')
cards = cards.replace("route('public.package.customize', ['product' => $product, 'line' => (string) Str::uuid()])", "$packageLinks[$product->id] ?? route($context['customize_route'], ['product' => $product, 'line' => (string) Str::uuid()])")
write('resources/views/components/ordering/package-cards.blade.php', "@props(['products', 'context', 'packageLinks' => []])\n"+cards+'\n')
index = index[:catalog_start]+"    <x-ordering.package-cards :products=\"$products\" :context=\"$context\" />"+index[catalog_end:]

bag_start=index.index('    @if(count($draftLines))')
bag_end=index.index('\n</div>\n\n<script>', bag_start)
bag=index[bag_start:bag_end]
bag=bag.replace('Your order</h2>', "{{ $context['summary_title'] }}</h2>")
bag=bag.replace('href="#main-content"', 'href="{{ route($context[\'select_route\']) }}#all-packages"')
bag=bag.replace("@if($entry['product']?->is_active)", "@if($entry['product'])")
bag=bag.replace("route('public.package.customize', ['product' => $item['product_id'], 'line' => $item['draft_key']])", "route($context['customize_route'], ['product' => $item['product_id'], 'line' => $item['draft_key'], 'draft_id' => $context['draft_id']])")
bag=bag.replace("route('public.package.remove', $item['draft_key'])", "route($context['remove_route'], $item['draft_key'])")
bag=bag.replace('                            @csrf', '                            @csrf\n                            @if($context[\'staff\'])<input type="hidden" name="draft_id" value="{{ $context[\'draft_id\'] }}">@endif')
bag=bag.replace("route('public.order.details')", "route($context['details_route'])")
bag=bag.replace('Continue to contact and pickup</a>', "{{ $context['continue_label'] }}</a>")
bag=bag.replace('<p>Order total <strong>', '@unless(collect($draftLines)->contains(fn($line) => $line[\'error\']))<p>Order total <strong>')
bag=bag.replace('</strong></p>\n                @unless', '</strong></p>@else<p class="field-error">Review affected packages to see the order total.</p>@endunless\n                @unless')
write('resources/views/components/ordering/saved-order.blade.php', "@props(['draftLines', 'context'])\n"+bag+'\n')
index=index[:bag_start]+'    <x-ordering.saved-order :draft-lines="$draftLines" :context="$context" />'+index[bag_end:]
progress_start=index.index('    <nav class="store-progress"')
progress_end=index.index('    </nav>', progress_start)+len('    </nav>')
index=index[:progress_start]+'    <x-ordering.progress :context="$context" :step="1" />'+index[progress_end:]
write('resources/views/public/index.blade.php', index)

fields=(root/'resources/views/partials/package-line-fields.blade.php').read_text(encoding='utf-8')
fields=fields.replace('                @unless ($staff)\n', '').replace('                @endunless\n', '')
fields=fields.replace(":src=\"'/storage/' + saved.staged_path\"", ":src=\"saved.preview_url || '/storage/' + saved.staged_path\"")
fields=fields.replace('<div><label :for="\'theme-', '<div><label :for="\'theme-')
write('resources/views/components/ordering/package-fields.blade.php', "@props(['staff' => false, 'customizing' => false])\n"+fields)
write('resources/views/partials/package-line-fields.blade.php', '<x-ordering.package-fields :staff="$staff ?? false" :customizing="$customizing ?? false" />\n')

customize=(root/'resources/views/public/customize.blade.php').read_text(encoding='utf-8')
form_start=customize.index('    <form method="POST"')
form_end=customize.index('    </form>',form_start)+len('    </form>')
form=customize[form_start:form_end]
form=form.replace("route('public.package.save', ['product' => $product, 'line' => $line])", "route($context['save_route'], ['product' => $product, 'line' => $line])")
form=form.replace("route('public.package.draft', ['product' => $product, 'line' => $line])", "route($context['editor_route'], ['product' => $product, 'line' => $line])")
form=form.replace("{{ Js::from(route('public.order.quote')) }}, false, {{ Js::from('bakery-package-'.$line) }}", "{{ Js::from($context['quote_url']) }}, {{ $context['staff'] ? 'true' : 'false' }}, {{ Js::from($context['browser_prefix'].'-'.$line) }}")
form=form.replace('          data-package-editor', '          data-package-editor\n          data-order-context="{{ $context[\'staff\'] ? \'staff\' : \'public\' }}"')
form=form.replace('        @csrf', '        @csrf\n        @if($context[\'staff\'])<input type="hidden" name="draft_id" value="{{ $context[\'draft_id\'] }}">@endif')
form=form.replace("@include('partials.package-line-fields', ['staff' => false, 'customizing' => true])", '<x-ordering.package-fields :staff="$context[\'staff\']" :customizing="true" />')
form=form.replace('Your package</h2>', "{{ $context['staff'] ? 'Package summary' : 'Your package' }}</h2>")
form=form.replace("route('public.order.index')", "route($context['select_route'])")
form=form.replace(':disabled="uploading || busy"', ':disabled="uploading || busy || unavailable"')
form=form.replace('<noscript>', "@if($context['staff'] && (!$product->is_active || $product->options->isEmpty()))<p class=\"field-error\" role=\"alert\">This package is unavailable. Your customization and photos are kept. Return to packages to remove it or select a replacement.</p>@endif\n        <noscript>")
form=form.replace("{{ Js::from($context['browser_prefix'].'-'.$line) }})", "{{ Js::from($context['browser_prefix'].'-'.$line) }}, {{ $product->is_active && $product->options->isNotEmpty() ? 'false' : 'true' }})")
write('resources/views/components/ordering/customization.blade.php', "@props(['context', 'products', 'product', 'line', 'editor'])\n"+form+'\n')
write('resources/views/public/customize.blade.php', '''@extends('public.layout')
@section('title', 'Customize your package · Aling Chona')
@section('content')
<script src="{{ asset('js/catalog-order.js') }}?v={{ filemtime(public_path('js/catalog-order.js')) }}"></script>
<div class="storefront">
    <a class="back-link" href="{{ route($context['select_route']) }}">← All packages</a>
    <header class="store-heading"><div><h1>Make it yours</h1><p>Choose the layers, extras and design for {{ $product->product_name }}.</p></div></header>
    <x-ordering.progress :context="$context" :step="2" />
    <x-ordering.customization :context="$context" :products="$products" :product="$product" :line="$line" :editor="$editor" />
</div>
@endsection
''')
write('resources/views/components/ordering/progress.blade.php', '''@props(['context', 'step'])
<nav class="store-progress" aria-label="Order progress">
    @if($step === 1)<span aria-current="step">1. Choose package</span>@else<a href="{{ route($context['select_route']) }}">1. Choose package</a>@endif
    <span @if($step === 2) aria-current="step" @endif>2. Customize</span>
    <span @if($step === 3) aria-current="step" @endif>3. {{ $context['staff'] ? 'Customer and pickup' : 'Contact & pickup' }}</span>
    @unless($context['staff'])<span>4. Staff review</span>@endunless
</nav>
''')
write('resources/views/admin/orders/create.blade.php', '''@extends('layouts.admin')
@section('title', 'Create staff order')
@section('content')
<div class="staff-ordering storefront" data-staff-draft-prefix="{{ $context['browser_prefix'] }}">
    <header class="page-heading"><div><h1 class="font-bold text-cocoa-600">Create staff order</h1><p class="mt-2">Choose packages for an order taken in person, by phone or messaging.</p></div><a class="ui-button quiet" href="{{ route('orders.index') }}">Back to orders</a></header>
    <x-ordering.progress :context="$context" :step="1" />
    <x-ordering.package-cards :products="$products" :context="$context" :package-links="$packageLinks" />
    <x-ordering.saved-order :draft-lines="$draftLines" :context="$context" />
</div>
<script>
try {
    const prefix = @js($context['browser_prefix']);
    const saved = new URLSearchParams(location.search).get('saved_line');
    const removed = @js(session('removed_staff_line'));
    if (saved || removed) sessionStorage.removeItem(prefix + '-' + (saved || removed));
    if (saved) document.getElementById('your-order')?.scrollIntoView({block:'start'});
} catch {}
</script>
@endsection
''')
write('resources/views/admin/orders/customize.blade.php', '''@extends('layouts.admin')
@section('title', 'Customize staff package')
@section('content')
<script src="{{ asset('js/catalog-order.js') }}?v={{ filemtime(public_path('js/catalog-order.js')) }}"></script>
<div class="staff-ordering storefront">
    <a class="back-link" href="{{ route('orders.create') }}">← Back to packages</a>
    <header class="store-heading"><div><h1>Customize package</h1><p>Enter the customer's layers, extras and design for {{ $product->product_name }}.</p></div></header>
    <x-ordering.progress :context="$context" :step="2" />
    <x-ordering.customization :context="$context" :products="$products" :product="$product" :line="$line" :editor="$editor" />
</div>
@endsection
''')
write('resources/views/admin/orders/details.blade.php', '''@extends('layouts.admin')
@section('title', 'Staff order details')
@section('content')
<div class="staff-ordering storefront">
    <header class="page-heading"><div><h1 class="font-bold text-cocoa-600">Customer and pickup</h1><p>Check the saved packages, customer and pickup schedule before creating the order.</p></div></header>
    <x-ordering.progress :context="$context" :step="3" />
    @include('partials.catalog-order-details', ['staff' => true, 'context' => $context])
</div>
@endsection
''')

for path in ['resources/views/admin/orders/show.blade.php', 'resources/views/partials/order-item-summary.blade.php']:
    edit(path, "asset('storage/' . $img->file_path)", '$img->url()')
edit('app/Http/Controllers/OrderController.php', "asset('storage/' . $img->file_path)", '$img->url()')
edit('resources/views/admin/orders/show.blade.php', 'Cancel this unpaid order? This closes the customer\'s request.', '''@if($order->hasVerifiedDeposit())
                                        Cancel this order? The verified 50% deposit of ₱{{ number_format($order->required_down_payment, 2) }} is retained as cancellation collection. The remaining balance is no longer due.
                                    @else
                                        Cancel this unpaid order? This closes the customer's request.
                                    @endif''')
edit('resources/views/admin/orders/show.blade.php', 'If funds arrived, I must verify them and keep this booking active.', 'If funds arrived, I must verify and record the exact deposit before cancelling under the retained-deposit policy.')
edit('resources/views/admin/orders/show.blade.php', 'Staff has confirmed this request. Recording the verified deposit secures the booking and allows preparation.', 'The Owner approved this staff order at creation. Record the verified Cash or GCash deposit to secure the booking and allow preparation.')
edit('resources/views/admin/orders/show.blade.php', '<select id="deposit-method" name="payment_method"', '<select id="deposit-method" x-model="method" name="payment_method"')
edit('resources/views/admin/orders/show.blade.php', 'class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 pt-2">', 'x-data="{ method: \'cash\' }" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 pt-2">')
edit('resources/views/admin/orders/show.blade.php', 'id="deposit-reference" type="text" name="reference_number" placeholder="Required if GCash"', 'id="deposit-reference" type="text" name="reference_number" :required="method === \'gcash\'" value="{{ old(\'reference_number\') }}" placeholder="Required if GCash"')

edit('app/Http/Controllers/PublicOrderController.php', "return view('public.index', compact('products', 'draft', 'draftLines'));", "$context = app(\\App\\Services\\PackageDraftService::class)->context($request, false, $draft);\n        return view('public.index', compact('products', 'draft', 'draftLines', 'context'));")
edit('app/Http/Controllers/PublicOrderController.php', "'line' => $line, 'editor' => $editor]);", "'line' => $line, 'editor' => $editor, 'context' => $editors->context($request, false)]);")
edit('app/Http/Controllers/PublicOrderController.php', "return view('public.details', compact('draft', 'quote'));", "$context = app(\\App\\Services\\PackageDraftService::class)->context($request, false, $draft);\n        return view('public.details', compact('draft', 'quote', 'context'));")

