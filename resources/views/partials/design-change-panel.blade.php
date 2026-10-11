@if (!$designChange['available'] || $designChange['latest'] || $designChange['history_expired_at'])
@php($isPendingOrHold = $designChange['pending'] || !$designChange['available'])
@php($latest = $designChange['latest'])
@php($pastChanges = collect($designChange['history'] ?? [])->reject(fn ($change) => $change['status'] === 'pending'))

@if ($isPendingOrHold)
{{-- Active Blocker: Pending review or record unavailable with hold --}}
<section id="design-change" class="checkout-card design-panel is-pending" aria-labelledby="design-change-heading">
    <div class="design-panel-heading">
        <h2 id="design-change-heading">
            <svg class="w-5 h-5 text-cocoa-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            <span>Design change awaiting review</span>
        </h2>
        @if($designChange['hold'])
            <span class="design-hold-badge">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Preparation paused</span>
            </span>
        @endif
    </div>

    @if (!$designChange['available'])
        <div class="design-hold" role="alert">
            <svg class="design-hold-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <div class="design-hold-text">
                <strong>Record unavailable.</strong> {{ $designChange['hold'] ? 'The bakery must restore it before preparation can continue.' : 'Restore the record to view its history.' }}
            </div>
        </div>
    @elseif ($designChange['pending'])
        @php($requestChange = $designChange['pending'])
        <div class="design-hold" role="status">
            <svg class="design-hold-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div class="design-hold-text">
                <strong>Awaiting review.</strong> Preparation stays paused until this request is approved, declined or withdrawn. The saved design remains in effect until approval.
            </div>
        </div>

        <div class="flex items-center gap-2 text-xs text-cocoa-500 font-medium">
            <svg class="w-3.5 h-3.5 text-cocoa-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>Requested {{ \Carbon\Carbon::parse($requestChange['submitted_at'])->timezone(config('bakery.pickup_timezone'))->format('M j, Y g:i A') }}</span>
        </div>

        @if($requestChange['reason'])
            <div class="design-decision-reason-box">Customer note: {{ $requestChange['reason'] }}</div>
        @endif

        @include('partials.design-change-comparison')

        @if ($staff)
            @can('review-design-changes')
                <div class="design-review-actions">
                    <div class="design-review-approve-box">
                        <form method="POST" action="{{ route('orders.designChanges.approve', ['order' => $order, 'change' => $requestChange['id']]) }}" data-action-form data-validation-active="{{ old('_workflow') === 'design-approve' ? 'true' : 'false' }}">
                            @csrf
                            <input type="hidden" name="_workflow" value="design-approve">
                            <label class="design-feasibility-card">
                                <input type="checkbox" name="feasibility_confirmed" value="1" required {{ old('_workflow') === 'design-approve' && old('feasibility_confirmed') ? 'checked' : '' }}>
                                <span>I can fulfil every proposed change at the saved price and pickup time. If baking has started, I checked what can still be changed.</span>
                            </label>
                            @if(old('_workflow') === 'design-approve')
                                @error('feasibility_confirmed')<p class="design-error" role="alert">{{ $message }}</p>@enderror
                            @endif
                            <div class="flex items-center gap-3 pt-1">
                                <button class="design-approve-btn" type="submit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Approve design change</span>
                                </button>
                                <span role="status" data-submit-status class="text-xs text-cocoa-500 font-medium"></span>
                            </div>
                        </form>
                    </div>

                    <details class="design-decline-details" data-design-decline {{ old('_workflow') === 'design-decline' ? 'open' : '' }}>
                        <summary class="design-decline-summary">
                            <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            <span>Decline design change with explanation</span>
                        </summary>
                        <form class="design-decline-form" method="POST" action="{{ route('orders.designChanges.decline', ['order' => $order, 'change' => $requestChange['id']]) }}" data-action-form data-validation-active="{{ old('_workflow') === 'design-decline' ? 'true' : 'false' }}">
                            @csrf
                            <input type="hidden" name="_workflow" value="design-decline">
                            <label class="design-decline-label" for="design-decline-reason">Reason shown to the customer (required)</label>
                            <textarea id="design-decline-reason" name="reason" rows="3" maxlength="1000" required class="design-textarea" placeholder="Explain any extra cost, package change or work that cannot be changed. The current design and price will continue.">{{ old('_workflow') === 'design-decline' ? old('reason') : '' }}</textarea>
                            <p class="design-muted">Explain any extra cost, package change or work that cannot be changed. The current design and price will continue.</p>
                            @error('reason')<p class="design-error" role="alert">{{ $message }}</p>@enderror
                            <div class="flex items-center gap-3 pt-1">
                                <button class="design-decline-btn" type="submit">
                                    <span>Decline with explanation</span>
                                </button>
                                <span role="status" data-submit-status class="text-xs text-rose-600 font-medium"></span>
                            </div>
                        </form>
                    </details>
                </div>
            @endcan
        @else
            <div class="design-withdraw-card">
                <div class="design-withdraw-text">
                    <p class="design-withdraw-title">Changed your mind?</p>
                    <p class="design-withdraw-desc">You can withdraw this request to resume preparation with your saved design immediately.</p>
                </div>
                <form method="POST" action="{{ route('public.designChanges.withdraw', ['token' => $order->private_token, 'change' => $requestChange['id']]) }}" data-action-form>
                    @csrf
                    <div class="flex items-center gap-2">
                        <button class="design-withdraw-btn" type="submit">
                            <span>Withdraw change request</span>
                        </button>
                        <span role="status" data-submit-status class="text-xs text-cocoa-500 font-medium"></span>
                    </div>
                </form>
            </div>
        @endif
    @endif

    @error('design_change')<p class="design-error" role="alert">{{ $message }}</p>@enderror
</section>

@else
{{-- Resolved / History State: Sleek, compact, single-surface card --}}
<section id="design-change" class="checkout-card design-panel is-resolved is-{{ $latest ? $latest['status'] : 'expired' }}" aria-labelledby="design-change-heading">
    @if ($designChange['history_expired_at'])
        <p class="design-muted">Design-change history expired {{ config('order-retention.design_history_days') }} days after order completion. The saved order details and payment records remain available.</p>
    @elseif ($latest)
        <div class="design-resolved-bar">
            <div class="design-resolved-status">
                <div class="design-resolved-icon">
                    @if($latest['status'] === 'approved')
                        <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    @elseif($latest['status'] === 'declined')
                        <svg class="w-4 h-4 text-rose-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                    @else
                        <svg class="w-4 h-4 text-cocoa-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @endif
                </div>
                <div class="design-resolved-text">
                    <div class="design-resolved-headline">
                        <h2 id="design-change-heading" class="design-resolved-title">
                            <span>Design change</span>
                            <span class="design-status-pill is-{{ $latest['status'] }}">{{ ucfirst($latest['status']) }}</span>
                        </h2>
                        @if(!empty($latest['decided_at']))
                            <span class="design-resolved-timestamp">
                                Decided {{ \Carbon\Carbon::parse($latest['decided_at'])->timezone(config('bakery.pickup_timezone'))->format('M j, Y g:i A') }}@if(!empty($latest['reviewer_name'])) by {{ $latest['reviewer_name'] }}@endif
                            </span>
                        @endif
                    </div>
                    <p class="design-resolved-desc">
                        {{ match($latest['status']) {
                            'approved' => 'Your saved design has been updated and is in effect for preparation.',
                            'declined' => 'The proposed change was declined. The saved design stays in effect.',
                            'withdrawn' => 'The change request was withdrawn. The saved design stays in effect.',
                            default => 'This order no longer accepts changes.'
                        } }}
                    </p>
                    @if(!empty($latest['reason']))
                        <div class="design-resolved-note">
                            <strong>{{ $latest['status'] === 'declined' ? 'Bakery reason' : 'Customer note' }}:</strong> {{ $latest['reason'] }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if($pastChanges->isNotEmpty())
            <div class="design-resolved-history">
                <details class="design-history">
                    <summary class="design-history-summary">
                        <div class="design-history-summary-left">
                            <svg class="w-3.5 h-3.5 text-cocoa-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Design change history ({{ $pastChanges->count() }})</span>
                        </div>
                        <svg class="w-3.5 h-3.5 text-cocoa-400 design-chevron-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <div class="design-history-list">
                        @foreach($pastChanges as $requestChange)
                            <details class="design-history-item">
                                <summary class="design-history-item-summary">
                                    <div class="flex items-center gap-2">
                                        <span class="design-status-pill is-{{ $requestChange['status'] }}">{{ ucfirst($requestChange['status']) }}</span>
                                        <span>Submitted {{ \Carbon\Carbon::parse($requestChange['submitted_at'])->timezone(config('bakery.pickup_timezone'))->format('M j, Y g:i A') }}</span>
                                    </div>
                                    <svg class="w-3.5 h-3.5 text-cocoa-400 design-chevron-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </summary>
                                <div class="design-history-item-content">
                                    @if($requestChange['decided_at'])
                                        <div class="design-history-meta">
                                            Decided {{ \Carbon\Carbon::parse($requestChange['decided_at'])->timezone(config('bakery.pickup_timezone'))->format('M j, Y g:i A') }}
                                            @if($requestChange['reviewer_name']) by {{ $requestChange['reviewer_name'] }}@endif
                                        </div>
                                    @endif
                                    @if($requestChange['reason'])
                                        <div class="design-decision-reason-box">Reason: {{ $requestChange['reason'] }}</div>
                                    @endif
                                    @include('partials.design-change-comparison')
                                </div>
                            </details>
                        @endforeach
                    </div>
                </details>
            </div>
        @endif
    @endif

    @error('design_change')<p class="design-error" role="alert">{{ $message }}</p>@enderror
</section>
@endif
@endif
