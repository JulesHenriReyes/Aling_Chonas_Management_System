@props(['id' => 'pickup-time', 'value' => ''])
@php
    [$opening, $closing] = \App\Support\PickupHours::bounds();
    $message = \App\Support\PickupHours::message();
    $value = is_string($value) ? $value : '';
    $options = \App\Support\PickupHours::options();
@endphp
<div class="pickup-time-picker" data-pickup-time data-options="{{ json_encode($options) }}" data-message="{{ $message }}">
    <label id="{{ $id }}-label" for="{{ $id }}" class="block text-xs font-semibold text-cocoa-700 uppercase tracking-wider mb-1.5">Pickup time <span class="text-red-500">*</span></label>
    <input id="{{ $id }}" name="pickup_time" type="time" min="{{ $opening }}" max="{{ $closing }}" step="60" required value="{{ $value }}" class="form-input-custom" aria-describedby="{{ $id }}-help {{ $id }}-error" @error('pickup_time') aria-invalid="true" @enderror data-time-value>
    <button id="{{ $id }}-trigger" type="button" class="form-input-custom pickup-time-trigger" aria-haspopup="dialog" aria-expanded="false" aria-controls="{{ $id }}-panel" aria-labelledby="{{ $id }}-label {{ $id }}-display" aria-describedby="{{ $id }}-help {{ $id }}-error" data-time-trigger hidden>
        <span id="{{ $id }}-display" data-time-display>Select pickup time</span>
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
    </button>
    <p id="{{ $id }}-help" class="text-xs text-cocoa-500 mt-1">Pickup available from {{ \App\Support\PickupHours::label($opening) }} to {{ \App\Support\PickupHours::label($closing) }}.</p>
    <p id="{{ $id }}-error" class="field-error" role="alert" data-error-for="pickup_time" data-time-error @unless($errors->has('pickup_time')) hidden @endunless>{{ $errors->first('pickup_time') }}</p>
    <div id="{{ $id }}-panel" class="pickup-time-panel" role="dialog" aria-modal="false" aria-labelledby="{{ $id }}-panel-heading" hidden data-time-panel>
        <div class="pickup-time-panel-heading">
            <h3 id="{{ $id }}-panel-heading">Select pickup time</h3>
            <button type="button" aria-label="Close pickup time picker" data-time-close><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
        </div>
        <div class="pickup-time-controls">
            <div><label for="{{ $id }}-period">AM/PM</label><select id="{{ $id }}-period" data-time-period><option value="">Select</option>@foreach (array_keys($options) as $period)<option value="{{ $period }}">{{ $period }}</option>@endforeach</select></div>
            <div><label for="{{ $id }}-hour">Hour</label><select id="{{ $id }}-hour" data-time-hour disabled><option value="">Select</option></select></div>
            <div><label for="{{ $id }}-minute">Minute</label><select id="{{ $id }}-minute" data-time-minute disabled><option value="">Select</option></select></div>
        </div>
        <div class="pickup-time-panel-footer">
            <span data-time-preview aria-live="polite">Choose AM or PM to begin.</span>
            <button type="button" data-time-done disabled>Done</button>
        </div>
    </div>
</div>
@once
    <script defer src="{{ asset('js/pickup-time-picker.js') }}?v={{ filemtime(public_path('js/pickup-time-picker.js')) }}"></script>
@endonce
