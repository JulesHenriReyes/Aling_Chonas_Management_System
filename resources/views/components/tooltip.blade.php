@props([
    'text' => '',
    'position' => 'top',
    'align' => 'top-right',
    'ariaLabel' => null,
])

@php
    $id = 'tooltip-' . Str::random(8);
@endphp

<div x-data="{ show: false }"
     x-id="['tooltip']"
     @mouseenter="show = true"
     @mouseleave="show = false"
     @focusin="show = true"
     @focusout="show = false"
     @click.outside="show = false"
     @keydown.escape.window="show = false"
     @scroll.window.capture.passive="if (show) show = false"
     @resize.window.passive="if (show) show = false"
     x-effect="if (typeof window.positionTooltip === 'function') { window.positionTooltip($el, $refs, show, '{{ $position }}', '{{ $align }}'); } else if (show) { const p = $refs.panel; if (p) p.style.visibility = 'visible'; } else { const p = $refs.panel; if (p) p.style.visibility = 'hidden'; }"
     {{ $attributes->class(['inline-flex items-center align-middle ui-tooltip']) }}>
    @if ($slot->isNotEmpty())
        <span tabindex="0"
              role="button"
              x-ref="trigger"
              aria-describedby="{{ $id }}"
              :aria-describedby="$id('tooltip')"
              @if($ariaLabel) aria-label="{{ $ariaLabel }}" @endif
              @click.prevent.stop="show = !show"
              @mousedown.stop
              @focus="show = true"
              @blur="show = false"
              @keydown.enter.prevent.stop="show = !show"
              @keydown.space.prevent.stop="show = !show"
              class="inline-flex items-center cursor-help focus:outline-none focus-visible:ring-1 focus-visible:ring-cocoa-400 rounded">
            {{ $slot }}
        </span>
    @else
        <button type="button"
                x-ref="trigger"
                aria-describedby="{{ $id }}"
                :aria-describedby="$id('tooltip')"
                aria-label="{{ $ariaLabel ?? ($text ?: 'More information') }}"
                @click.prevent.stop="show = !show"
                @mousedown.stop
                @focus="show = true"
                @blur="show = false"
                class="inline-flex items-center justify-center w-4 h-4 text-cocoa-400 hover:text-cocoa-600 focus:outline-none focus-visible:ring-1 focus-visible:ring-cocoa-400 rounded-full transition-colors cursor-help">
            <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" focusable="false">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
            </svg>
        </button>
    @endif

    <div x-show="show"
         x-cloak
         x-ref="panel"
         style="visibility: hidden;"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         id="{{ $id }}"
         :id="$id('tooltip')"
         role="tooltip"
         aria-label="{{ $ariaLabel ?? $text }}"
         class="fixed z-[9999] bg-cocoa-800 text-white rounded-lg p-2.5 text-xs shadow-lg leading-relaxed w-max max-w-xs sm:max-w-sm max-w-[calc(100vw-2rem)] ui-tooltip-panel">
        {{ $content ?? $text }}
    </div>
</div>
