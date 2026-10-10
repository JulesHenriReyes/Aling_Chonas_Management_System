@if ($paginator->hasPages())
    <nav class="bakery-pagination" role="navigation" aria-label="{{ __('Pagination Navigation') }}">
        <p class="pagination-summary">
            {{ __('Showing') }} <strong>{{ $paginator->firstItem() }}</strong>–<strong>{{ $paginator->lastItem() }}</strong>
            {{ __('of') }} <strong>{{ $paginator->total() }}</strong> {{ __('results') }}
        </p>

        <div class="pagination-controls">
            @if ($paginator->onFirstPage())
                <span class="pagination-button pagination-arrow is-disabled" role="link" aria-disabled="true" aria-label="{{ __('Previous page') }}">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="m12 5-5 5 5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span class="pagination-direction-text">{{ __('Previous') }}</span>
                </span>
            @else
                <a class="pagination-button pagination-arrow" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('Previous page') }}">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="m12 5-5 5 5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span class="pagination-direction-text">{{ __('Previous') }}</span>
                </a>
            @endif

            <span class="pagination-mobile-count">{{ __('Page') }} {{ $paginator->currentPage() }} {{ __('of') }} {{ $paginator->lastPage() }}</span>

            <div class="pagination-pages">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="pagination-ellipsis" aria-hidden="true">{{ $element }}</span>
                    @endif
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="pagination-button is-current" aria-current="page" aria-label="{{ __('Page :page', ['page' => $page]) }}">{{ $page }}</span>
                            @else
                                <a class="pagination-button" href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            @if ($paginator->hasMorePages())
                <a class="pagination-button pagination-arrow" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('Next page') }}">
                    <span class="pagination-direction-text">{{ __('Next') }}</span>
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="m8 5 5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
            @else
                <span class="pagination-button pagination-arrow is-disabled" role="link" aria-disabled="true" aria-label="{{ __('Next page') }}">
                    <span class="pagination-direction-text">{{ __('Next') }}</span>
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="m8 5 5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
            @endif
        </div>
    </nav>
@endif
