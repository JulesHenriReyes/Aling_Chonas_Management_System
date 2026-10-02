@props(['column', 'label'])
@php($active = request('sort') === $column)
<a class="sort-heading" href="{{ request()->fullUrlWithQuery(['sort' => $column, 'direction' => $active && request('direction', 'asc') === 'asc' ? 'desc' : 'asc', 'page' => null]) }}" aria-label="Sort by {{ $label }}{{ $active ? ', currently '.request('direction', 'asc').'ending' : '' }}">{{ $label }} <span aria-hidden="true">{{ $active ? (request('direction', 'asc') === 'asc' ? '↑' : '↓') : '↕' }}</span></a>
