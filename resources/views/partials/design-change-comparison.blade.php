<div class="design-comparison-list">
    @foreach ($requestChange['before'] as $position => $before)
        @php($proposed = $requestChange['proposed'][$position])
        <section class="design-package" aria-label="Design comparison for {{ $before['name'] }}">
            <div class="design-package-header">
                <svg class="w-4 h-4 text-cocoa-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <h3>{{ $before['name'] }}</h3>
            </div>

            <div class="design-comparison">
                @foreach (['before' => $before, 'proposed' => $proposed] as $side => $line)
                    @php($isProposed = $side === 'proposed')
                    <div class="design-comparison-side {{ $isProposed ? 'is-proposed' : 'is-before' }}">
                        <div class="design-side-header">
                            <h4 class="design-side-title">{{ $isProposed ? 'Proposed design' : 'Design when requested' }}</h4>
                            <span class="design-side-badge">{{ $isProposed ? 'Requested' : 'Saved baseline' }}</span>
                        </div>

                        <div class="design-field-group">
                            <div class="design-field-item">
                                <div class="design-field-label">
                                    <span>Theme / colors</span>
                                    @if ($isProposed && ($line['themes'] ?? '') !== ($before['themes'] ?? ''))
                                        <span class="design-diff-tag is-updated">Updated</span>
                                    @endif
                                </div>
                                @if (filled($line['themes']))
                                    <div class="design-field-value">{{ $line['themes'] }}</div>
                                @else
                                    <div class="design-field-value is-empty">No theme specified</div>
                                @endif
                            </div>

                            <div class="design-field-item">
                                <div class="design-field-label">
                                    <span>Instructions & piping</span>
                                    @if ($isProposed && ($line['special_request'] ?? '') !== ($before['special_request'] ?? ''))
                                        <span class="design-diff-tag is-updated">Updated</span>
                                    @endif
                                </div>
                                @if (filled($line['special_request']))
                                    <div class="design-field-value">{{ $line['special_request'] }}</div>
                                @else
                                    <div class="design-field-value is-empty">No special instructions</div>
                                @endif
                            </div>
                        </div>

                        <div class="design-photo-section">
                            <p class="design-photo-heading">
                                <span>Reference photos</span>
                                <span class="font-normal text-cocoa-400">({{ count($line['references']) }})</span>
                            </p>

                            @if (!$line['references'])
                                <div class="design-field-value is-empty">No reference photos</div>
                            @elseif ($designChange['photos_expired_at'])
                                <p class="design-muted">Reference photos expired after the completed-order retention period.</p>
                            @else
                                <div class="design-photo-grid">
                                    @foreach ($line['references'] as $reference)
                                        @php($url = $staff
                                            ? route('orders.designChanges.reference', ['order' => $order, 'change' => $requestChange['id'], 'reference' => $reference['key']])
                                            : route('public.designChanges.reference', ['token' => $order->private_token, 'change' => $requestChange['id'], 'reference' => $reference['key']]))
                                        <a class="design-photo-card" href="{{ $url }}" target="_blank" rel="noopener noreferrer" title="View photo: {{ $reference['name'] }}">
                                            <div class="design-photo-thumb-wrap">
                                                <img src="{{ $url }}" loading="lazy" width="96" height="96" alt="{{ $reference['name'] }}">
                                                @if ($isProposed)
                                                    @if (in_array($reference['file_path'], array_column($before['references'], 'file_path'), true))
                                                        <span class="design-photo-badge is-kept">Kept</span>
                                                    @else
                                                        <span class="design-photo-badge is-added">Added</span>
                                                    @endif
                                                @elseif (!in_array($reference['file_path'], array_column($proposed['references'], 'file_path'), true))
                                                    <span class="design-photo-badge is-removed">Removal requested</span>
                                                @endif
                                            </div>
                                            <span class="design-photo-name">{{ $reference['name'] }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
