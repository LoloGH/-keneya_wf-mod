{{-- Logo KEneYa WorkFlow, en SVG inline : aucun fichier binaire a servir, et
     il suit la couleur du texte environnant. --}}
@props(['class' => ''])

<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 40 40" role="img"
     aria-label="{{ config('keneya.name') }}" width="32" height="32" fill="none">
    <circle cx="20" cy="20" r="18" stroke="currentColor" stroke-width="2.5" opacity=".55" />
    <path d="M20 10v20M10 20h20" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" />
    <path d="M27 13.5 13 26.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity=".5" />
</svg>
