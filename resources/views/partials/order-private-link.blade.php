<section class="checkout-card p-5 space-y-3" x-data="{ copied: false, failed: false }" aria-labelledby="save-order">
    <div class="flex items-center gap-2.5 pb-2.5 border-b border-cocoa-100">
        <div class="w-7 h-7 rounded-lg bg-cocoa-50 text-cocoa-600 flex items-center justify-center shrink-0 border border-cocoa-100">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
        </div>
        <h2 id="save-order" class="font-bold text-cocoa-700 text-sm">Keep your order link</h2>
    </div>
    <p class="text-xs text-cocoa-400">Save or bookmark this private link to check your order progress at any time.</p>
    <div>
        <label for="private-order-link" class="block text-xs font-semibold text-cocoa-700 uppercase tracking-wider mb-1.5 inline-flex items-center gap-1">
            Private order link
            <x-tooltip text="Bookmark or copy this unique private link to track your order status and upload receipts without needing an account." />
        </label>
        <input id="private-order-link" type="text" readonly value="{{ route('public.order.payment', $order->private_token) }}" class="form-input-custom font-mono text-xs bg-cream-50" x-ref="orderLink">
    </div>
    <div class="flex flex-wrap items-center gap-2.5 pt-1">
        <button type="button" class="px-3.5 py-1.5 text-xs font-semibold bg-white border border-cocoa-200 hover:bg-cream-100 text-cocoa-700 rounded-lg transition" @click="navigator.clipboard ? navigator.clipboard.writeText($refs.orderLink.value).then(() => { copied = true; failed = false; setTimeout(() => copied = false, 3000) }).catch(() => { failed = true }) : failed = true">Copy order link</button>
        <a href="{{ route('public.order.saveLink', $order->private_token) }}" class="px-3.5 py-1.5 text-xs font-semibold text-cocoa-600 hover:text-cocoa-800 underline transition inline-flex items-center">Save order link</a>
    </div>
    <p role="status" x-show="copied" x-cloak class="text-xs text-emerald-700 font-medium">Order link copied to clipboard.</p>
    <p role="status" x-show="failed" x-cloak class="text-xs text-amber-700">Copy is unavailable. Select the link above or use Save order link.</p>
</section>
