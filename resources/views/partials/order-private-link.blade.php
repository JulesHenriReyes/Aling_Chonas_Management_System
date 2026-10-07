<div x-data="{
        copied: false,
        hasCopied: false,
        autoCopied: false,
        showUrl: false,
        dismissed: false,
        link: '{{ route('public.order.payment', $order->private_token) }}',
        copyViaFallback(text) {
            try {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.setAttribute('readonly', '');
                ta.style.position = 'fixed';
                ta.style.left = '-9999px';
                ta.style.top = '0';
                document.body.appendChild(ta);
                ta.focus();
                ta.select();
                const success = document.execCommand('copy');
                document.body.removeChild(ta);
                return success;
            } catch (e) {
                return false;
            }
        },
        init() {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(this.link).then(() => {
                    this.copied = true;
                    this.hasCopied = true;
                    this.autoCopied = true;
                    setTimeout(() => {
                        this.copied = false;
                        this.autoCopied = false;
                    }, 4000);
                }).catch(() => {
                    this.autoCopied = false;
                });
            }
        },
        copy() {
            const self = this;
            const onSuccess = () => {
                self.copied = true;
                self.hasCopied = true;
                self.autoCopied = false;
                setTimeout(() => { self.copied = false; }, 3000);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(this.link)
                    .then(onSuccess)
                    .catch(() => {
                        if (self.copyViaFallback(self.link)) {
                            onSuccess();
                        }
                    });
            } else {
                if (this.copyViaFallback(this.link)) {
                    onSuccess();
                }
            }
        }
    }"
    x-show="!dismissed"
    x-cloak
    class="auto-copy-link-banner rounded-xl bg-cream-50 border border-cocoa-200/80 px-3 py-2 sm:px-3.5 sm:py-2.5 shadow-2xs transition-all duration-200"
    role="status"
    aria-live="polite">
    <div class="flex items-center justify-between gap-2.5 flex-wrap sm:flex-nowrap">
        <div class="flex items-center gap-2 min-w-0">
            <!-- Dynamic Status Icon -->
            <div class="w-6 h-6 rounded-md flex items-center justify-center shrink-0 border transition-colors duration-200"
                 :class="(copied || hasCopied) ? 'bg-emerald-100 text-emerald-800 border-emerald-200/70' : 'bg-cocoa-100/70 text-cocoa-700 border-cocoa-200'">
                <template x-if="copied || hasCopied">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                </template>
                <template x-if="!copied && !hasCopied">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                </template>
            </div>
            <div class="flex items-center gap-1.5 flex-wrap min-w-0 text-xs">
                <span class="font-bold text-cocoa-800 whitespace-nowrap">Private order link</span>
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 border border-emerald-300/60 whitespace-nowrap" x-show="copied">
                    <span x-text="autoCopied ? 'Auto-copied' : 'Copied!'">Auto-copied</span>
                </span>
                <x-tooltip text="Bookmark or copy this unique private link to track your order status and upload receipts without needing an account." />
            </div>
        </div>
        <div class="flex items-center gap-1.5 shrink-0 ml-auto sm:ml-0">
            <button type="button"
                    @click="copy()"
                    class="px-2.5 py-1 text-xs font-semibold bg-white border border-cocoa-200 hover:bg-cream-100 active:bg-cream-200 text-cocoa-700 rounded-lg transition inline-flex items-center gap-1 shadow-2xs cursor-pointer focus:outline-none"
                    :aria-label="copied ? 'Link copied' : (hasCopied ? 'Copy again' : 'Copy link')">
                <svg class="w-3 h-3 text-cocoa-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <span x-text="copied ? 'Copied' : (hasCopied ? 'Copy again' : 'Copy')">Copy</span>
            </button>
            <button type="button"
                    @click="showUrl = !showUrl"
                    class="px-2 py-1 text-xs text-cocoa-600 hover:text-cocoa-800 hover:bg-cream-100 rounded-md transition cursor-pointer"
                    :aria-expanded="showUrl">
                <span x-text="showUrl ? 'Hide' : 'View'">View</span>
            </button>
            <a href="{{ route('public.order.saveLink', $order->private_token) }}"
               class="px-2 py-1 text-xs text-cocoa-600 hover:text-cocoa-800 underline transition">
                Save
            </a>
            <button type="button"
                    @click="dismissed = true"
                    class="text-cocoa-400 hover:text-cocoa-600 p-1 rounded-md transition cursor-pointer"
                    aria-label="Dismiss link notification">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    <!-- Collapsible raw link display -->
    <div x-show="showUrl"
         x-cloak
         x-transition
         class="mt-2 pt-2 border-t border-cocoa-100 flex items-center gap-2 text-xs">
        <label for="auto-copy-raw-link" class="sr-only">Order URL</label>
        <input id="auto-copy-raw-link"
               type="text"
               readonly
               :value="link"
               @focus="$event.target.select()"
               class="flex-1 font-mono text-[11px] bg-white text-cocoa-700 px-2.5 py-1 rounded-lg border border-cocoa-200 select-all focus:outline-none">
        <button type="button"
                @click="copy()"
                class="px-2.5 py-1 bg-cocoa-600 hover:bg-cocoa-700 text-white rounded-lg font-medium text-[11px] transition shrink-0 cursor-pointer">
            Copy
        </button>
    </div>
</div>
