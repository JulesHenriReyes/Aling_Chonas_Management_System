@props(['inverse' => false, 'compact' => false])
<span {{ $attributes->class(['bakery-brand', 'bakery-brand-inverse' => $inverse, 'bakery-brand-compact' => $compact]) }}>
    <span class="bakery-brand-name">Aling Chona<span class="bakery-brand-dot" aria-hidden="true">.</span></span>
    <span class="bakery-brand-caption">Cakes &amp; Cupcakes</span>
</span>
