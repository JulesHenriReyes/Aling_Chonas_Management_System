@extends('public.layout')
@section('title', 'Request design change · Aling Chona')
@section('content')
<link rel="stylesheet" href="{{ asset('css/design-changes.css') }}?v={{ filemtime(public_path('css/design-changes.css')) }}">

<div class="design-editor max-w-4xl mx-auto py-2">
    {{-- Top Navigation --}}
    <div class="design-editor-top-nav">
        <a href="{{ route('public.order.payment', $order->private_token) }}" class="design-back-btn">
            <svg class="w-4 h-4 text-cocoa-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <span>Back to order details</span>
        </a>
    </div>

    {{-- Editorial Header --}}
    <header class="design-editor-header">
        <h1>Request a Design Change</h1>
        <div class="design-editor-meta-bar">
            <span class="design-meta-pill font-mono font-bold">
                {{ $order->order_number }}
            </span>
            <span class="design-meta-pill">
                <svg class="w-3.5 h-3.5 text-cocoa-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span>Pickup: {{ $order->pickup_date->format('M j, Y') }}</span>
            </span>
        </div>
    </header>

    {{-- Concierge Guidance Card --}}
    <div class="design-concierge-card">
        <div class="design-concierge-icon">
            <svg class="w-5 h-5 text-cocoa-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <div class="design-concierge-text">
            <div class="design-concierge-title">How design adjustments work</div>
            <p class="design-concierge-body">
                Update your desired theme, piping instructions, or reference photos below. Our bakery reviews all requests to ensure they fit your selected package. Any change requiring additional pricing or a different package size will be declined with an explanation.
            </p>
        </div>
    </div>

    {{-- Main Form --}}
    <form method="POST" action="{{ route('public.designChanges.submit', $order->private_token) }}" data-design-editor data-save-url="{{ route('public.designChanges.draft', $order->private_token) }}" data-upload-url="{{ route('public.designChanges.upload', $order->private_token) }}" class="space-y-6">
        @csrf
        <input type="hidden" name="draft_id" value="{{ $draft['id'] }}">

        @if($errors->any())
            <div class="design-error-summary" role="alert">
                <div class="font-bold">Please check your request before submitting</div>
                <ul>
                    @foreach($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('public.designChanges.form', ['token' => $order->private_token, 'restart' => 1]) }}">Start again with the latest saved design</a>
            </div>
        @endif

        @foreach($draft['snapshot'] as $line)
            @php($item = old('items.'.$line['id'], $draft['items'][$line['id']]))
            <section class="checkout-card design-editor-package" data-design-line="{{ $line['id'] }}" aria-labelledby="design-line-{{ $line['id'] }}">
                <h2 id="design-line-{{ $line['id'] }}">
                    <svg class="w-5 h-5 text-cocoa-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <span>{{ $line['name'] }}</span>
                </h2>

                {{-- Accordion: Current Saved Design --}}
                <details class="design-current-accordion">
                    <summary class="design-current-summary">
                        <div class="design-current-summary-left">
                            <span class="text-xs uppercase tracking-wider text-cocoa-400 font-bold">Currently Saved Baseline:</span>
                            <span>View what is on file</span>
                        </div>
                        <svg class="design-chevron-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </summary>
                    <div class="design-current-content">
                        <dl>
                            <div>
                                <dt>Current Theme / Colors</dt>
                                <dd>{{ $line['themes'] ?: 'No theme specified.' }}</dd>
                            </div>
                            <div>
                                <dt>Current Instructions & Inscriptions</dt>
                                <dd>{{ $line['special_request'] ?: 'No instructions specified.' }}</dd>
                            </div>
                        </dl>
                    </div>
                </details>

                <div class="design-editor-fields">
                    <div class="design-input-group">
                        <label class="design-label" for="theme-{{ $line['id'] }}">
                            <span>Proposed theme & color palette</span>
                        </label>
                        <input id="theme-{{ $line['id'] }}" name="items[{{ $line['id'] }}][themes]" value="{{ $item['themes'] ?? '' }}" maxlength="255" aria-describedby="theme-help-{{ $line['id'] }}" class="design-input" placeholder="e.g. Sage green and cream with small buttercream blossoms">
                        <p id="theme-help-{{ $line['id'] }}" class="design-help-text">Specify color accents, frosting shades, or decorative themes.</p>
                        @error('items.'.$line['id'].'.themes')<p class="design-error" role="alert">{{ $message }}</p>@enderror
                    </div>

                    <div class="design-input-group">
                        <label class="design-label" for="instructions-{{ $line['id'] }}">
                            <span>Proposed piping & special instructions</span>
                        </label>
                        <textarea id="instructions-{{ $line['id'] }}" name="items[{{ $line['id'] }}][special_request]" rows="4" maxlength="1000" class="design-textarea" placeholder="e.g. Please pipe 'Happy 60th Birthday Nanay!' in gold cursive on the top surface. Add pearl accents around the base tier.">{{ $item['special_request'] ?? '' }}</textarea>
                        <p class="design-help-text">Include specific cake inscriptions, greeting text, or special placement details.</p>
                        @error('items.'.$line['id'].'.special_request')<p class="design-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                </div>

                <fieldset class="design-fieldset">
                    <legend class="design-legend">
                        <svg class="w-4 h-4 text-cocoa-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span>Reference & Inspiration Photos</span>
                    </legend>
                    <p class="design-help-text">Keep up to five photos per package. Uncheck any saved photo to request its removal.</p>

                    @if($line['references'])
                        <div class="design-photo-choices">
                            @foreach($line['references'] as $reference)
                                <label class="design-photo-choice">
                                    <img src="{{ route('public.order.reference', ['token' => $order->private_token, 'image' => $reference['id']]) }}" alt="{{ $reference['name'] }}" loading="lazy" width="96" height="96">
                                    <span>{{ $reference['name'] }}</span>
                                    <span class="design-check">
                                        <input type="checkbox" name="items[{{ $line['id'] }}][keep_images][]" value="{{ $reference['id'] }}" {{ in_array($reference['id'], $item['keep_images'] ?? []) ? 'checked' : '' }}>
                                        <span>Keep photo</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endif

                    <div data-staged-images class="design-photo-choices">
                        @foreach($item['staged_images'] ?? [] as $key)
                            @if(isset($draft['uploads'][$key]))
                                <label class="design-photo-choice">
                                    <img src="{{ route('public.designChanges.draftReference', ['token' => $order->private_token, 'draft' => $draft['id'], 'reference' => $key]) }}" alt="{{ $draft['uploads'][$key]['name'] }}" width="96" height="96">
                                    <span>{{ $draft['uploads'][$key]['name'] }}</span>
                                    <span class="design-check">
                                        <input type="checkbox" name="items[{{ $line['id'] }}][staged_images][]" value="{{ $key }}" checked>
                                        <span>New upload</span>
                                    </span>
                                </label>
                            @endif
                        @endforeach
                    </div>

                    {{-- Upload Dropzone --}}
                    <div class="design-upload-zone">
                        <svg class="design-upload-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <div class="design-upload-prompt">Click to add photos or drag files here</div>
                        <div class="design-upload-meta">JPG, PNG or WebP · Up to 5 MB each · Up to 5 photos total</div>
                        <input id="photos-{{ $line['id'] }}" type="file" accept="image/jpeg,image/png,image/webp" multiple data-design-upload aria-describedby="upload-status-{{ $line['id'] }}">
                    </div>
                    <p id="upload-status-{{ $line['id'] }}" data-upload-status role="status" class="design-muted text-center"></p>
                </fieldset>
            </section>
        @endforeach

        {{-- Submission Footer Card --}}
        <section class="checkout-card design-submit">
            <label class="design-ack-card">
                <input type="checkbox" name="pause_acknowledged" value="1" required {{ old('pause_acknowledged') ? 'checked' : '' }}>
                <span>I understand baking preparation will pause while the bakery reviews this request. The current saved design remains in effect until approved.</span>
            </label>
            @error('pause_acknowledged')<p class="design-error" role="alert">{{ $message }}</p>@enderror

            <p data-design-error class="design-error" role="alert" hidden></p>

            <div class="design-submit-row">
                <div class="design-submit-actions">
                    <button class="design-submit-btn ui-button primary" type="submit">
                        <span>Submit change request</span>
                    </button>
                    <a class="design-cancel-btn ui-button quiet" href="{{ route('public.order.payment', $order->private_token) }}">
                        <span>Cancel & back to order</span>
                    </a>
                </div>
                <div class="design-draft-indicator">
                    <span class="design-draft-dot"></span>
                    <span data-draft-status role="status">Only submission pauses preparation.</span>
                </div>
            </div>

            <noscript>
                <p class="text-xs text-cocoa-500">Text and saved photo changes can be submitted here. Enable JavaScript to upload new photos and save an unfinished draft automatically.</p>
            </noscript>
        </section>
    </form>
</div>
<script defer src="{{ asset('js/design-changes.js') }}"></script>
@endsection
