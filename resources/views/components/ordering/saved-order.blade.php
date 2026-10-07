@props(['draftLines', 'context'])
    @if(count($draftLines))
        <section class="order-bag" id="your-order" aria-labelledby="bag-title"
                 x-data="{
                     confirmRemove: null,
                     removeTrigger: null,
                     promptRemove(lineKey, packageName) {
                         this.removeTrigger = document.activeElement;
                         this.confirmRemove = { lineKey, packageName };
                         this.$nextTick(() => this.$refs.keepPackage.focus());
                     },
                     cancelRemove() {
                         this.confirmRemove = null;
                         this.removeTrigger?.focus();
                     }
                 }">
            <div class="workspace-heading">
                <h2 id="bag-title">{{ $context['summary_title'] }}</h2>
                <a class="ui-button quiet" href="{{ route($context['select_route']) }}#all-packages">Add another package</a>
            </div>

            @foreach($draftLines as $entry)
                @php($item = $entry['item'])
                @php($pkgName = $entry['product']?->product_name ?? 'this package')
                <article class="bag-line">
                    <div class="bag-line-content" style="display:flex; gap:0.875rem; align-items:flex-start;">
                        @if($entry['product']?->photo_path)
                            <img src="{{ asset('storage/'.$entry['product']->photo_path) }}" alt="{{ $entry['product']->product_name }}" style="width:3.5rem; height:3.5rem; border-radius:0.5rem; object-fit:cover; border:1px solid var(--bakery-line); flex-shrink:0;">
                        @else
                            <div style="width:3.5rem; height:3.5rem; border-radius:0.5rem; background:var(--bakery-bg); border:1px solid var(--bakery-line); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                <x-icon name="cake" style="width:1.75rem; height:1.75rem; color:var(--bakery-muted);" />
                            </div>
                        @endif
                        <div>
                            <h3>{{ $entry['product']?->product_name ?? 'Unavailable package' }} × {{ $item['quantity'] }}</h3>
                            @if($entry['quote'])
                                <p>{{ $entry['quote']['lines'][0]['layers'] }} layer(s) · ₱{{ number_format($entry['quote']['total'], 2) }}</p>
                            @endif
                            @if($item['themes'] ?? null)
                                <p>{{ $item['themes'] }}</p>
                            @endif
                            @foreach($entry['quote']['lines'][0]['add_ons'] ?? [] as $extra)
                                <p class="form-hint">{{ $extra['name_snapshot'] }} × {{ $extra['quantity'] }} · ₱{{ number_format($extra['unit_price'] * $extra['quantity'], 2) }}</p>
                            @endforeach
                            @foreach($item['staged_images'] ?? [] as $image)
                                <p class="form-hint">Reference: {{ $image['original_filename'] }}</p>
                            @endforeach
                            @if($entry['error'])
                                <p class="field-error">{{ $entry['error'] }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="workspace-actions">
                        @if($entry['product'])
                            <a class="ui-button" href="{{ route($context['customize_route'], ['product' => $item['product_id'], 'line' => $item['draft_key'], 'draft_id' => $context['draft_id']]) }}">Edit</a>
                        @endif
                        <form id="remove-package-form-{{ $item['draft_key'] }}" method="POST" action="{{ route($context['remove_route'], $item['draft_key']) }}">
                            @csrf
                            @if($context['staff'])<input type="hidden" name="draft_id" value="{{ $context['draft_id'] }}">@endif
                            <button type="button"
                                    @click="promptRemove('{{ $item['draft_key'] }}', @js($pkgName))"
                                    class="ui-button quiet text-red-700 hover:text-red-900 focus:outline-none">
                                Remove
                            </button>
                            <noscript>
                                <button class="ui-button quiet">Remove</button>
                            </noscript>
                        </form>
                    </div>
                </article>
            @endforeach

            <div class="bag-total">
                @unless(collect($draftLines)->contains(fn($line) => $line['error']))<p>Order total <strong>₱{{ number_format(collect($draftLines)->sum(fn($line) => $line['quote']['total'] ?? 0), 2) }}</strong></p>@else<p class="field-error">Review affected packages to see the order total.</p>@endunless
                @unless(collect($draftLines)->contains(fn($line) => $line['error']))
                    <a class="ui-button primary" href="{{ route($context['details_route']) }}">{{ $context['continue_label'] }}</a>
                @endunless
            </div>

            <!-- In-App Remove Package Confirmation Modal -->
            <template x-teleport="body">
                <div x-show="confirmRemove"
                     x-cloak
                     style="display: none;"
                     class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
                     @keydown.escape.window="cancelRemove()"
                     @keydown.tab="const controls = $event.currentTarget.querySelectorAll('button'); if ($event.shiftKey && document.activeElement === controls[0]) { $event.preventDefault(); controls[1].focus(); } else if (!$event.shiftKey && document.activeElement === controls[1]) { $event.preventDefault(); controls[0].focus(); }"
                     role="dialog"
                     aria-modal="true"
                     aria-labelledby="remove-modal-title">
                    <div class="relative bg-white rounded-2xl shadow-2xl border border-cocoa-100 max-w-sm w-full p-6 text-center transform transition-all"
                         @click.outside="cancelRemove()">
                        
                        <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-700 flex items-center justify-center mx-auto mb-3.5 border border-rose-100">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </div>

                        <h3 id="remove-modal-title" class="text-base font-bold text-cocoa-800">Remove package?</h3>
                        
                        <p class="text-xs text-cocoa-500 mt-2 leading-relaxed">
                            Are you sure you want to remove <span class="font-semibold text-cocoa-700" x-text="confirmRemove?.packageName"></span> from your order?
                        </p>

                        <div class="mt-6 flex items-center justify-center gap-3">
                            <button type="button"
                                    x-ref="keepPackage"
                                    @click="cancelRemove()"
                                    class="ui-button quiet">
                                Keep package
                            </button>

                            <button type="button"
                                    @click="if (confirmRemove) { document.getElementById('remove-package-form-' + confirmRemove.lineKey)?.submit(); }"
                                    class="ui-button bg-red-700 hover:bg-red-800 text-white">
                                Remove package
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </section>
    @endif
