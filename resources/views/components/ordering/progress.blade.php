@props(['context', 'step'])
<nav class="store-progress" aria-label="Order progress">
    @if($step === 1)<span aria-current="step">1. Choose package</span>@else<a href="{{ route($context['select_route']) }}">1. Choose package</a>@endif
    <span @if($step === 2) aria-current="step" @endif>2. Customize</span>
    <span @if($step === 3) aria-current="step" @endif>3. {{ $context['staff'] ? 'Customer and pickup' : 'Contact & pickup' }}</span>
    @unless($context['staff'])<span>4. Staff review</span>@endunless
</nav>
