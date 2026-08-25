{{--
    Logo KEneYa WorkFlow, redessine en SVG inline : net a toute taille, aucun
    binaire a servir, et deux variantes de couleur.

    - variant="color" : bleu nuit et vert, pour les fonds clairs.
    - variant="light" : memes formes en blanc et vert clair, pour les fonds
      sombres (barre de navigation, ecran de salle d'attente).

    Le mot-symbole (« KƐNƐYA WORKFLOW ») n'apparait qu'avec `lockup` ; ailleurs
    seul le monogramme est repris, pour rester compact.

        <x-brand-logo />                          monogramme couleur
        <x-brand-logo variant="light" />          monogramme clair
        <x-brand-logo lockup variant="color" />   logo complet

    Les coordonnees reprennent celles du fichier fourni par le porteur du
    projet, dans un repere de 1250 unites.
--}}
@props([
    'variant' => 'color',
    'lockup' => false,
    'title' => null,
])

@php
    $light = $variant === 'light';

    // Deux jeux de couleurs pour les memes formes.
    $ink = $light ? '#FFFFFF' : '#1D3A5C';    // le K, le point d'appui
    $accent = $light ? '#5FD3AA' : '#16A075'; // la croix, l'arc, le W
    $faded = $light ? '#9FE3C8' : '#93C6B4';  // le dernier point, en retrait

    $label = $title ?? config('keneya.name');
    $id = 'logo-'.\Illuminate\Support\Str::random(6);
@endphp

<svg {{ $attributes->merge(['class' => 'brand-logo'.($lockup ? ' brand-logo--lockup' : '')]) }}
     viewBox="{{ $lockup ? '130 170 2320 880' : '130 170 1050 880' }}"
     role="img" aria-labelledby="{{ $id }}" fill="none">
    <title id="{{ $id }}">{{ $label }}</title>

    {{-- L'arc coiffe la croix : demi-cercle passant par ses deux extremites et
         son sommet, releves sur le fichier d'origine. --}}
    <path d="M262 530 A344 344 0 0 1 950 540" stroke="{{ $accent }}" stroke-width="30"
          stroke-linecap="round" fill="none" />

    <path d="M602 355 h115 v67 h68 v115 h-68 v68 h-115 v-68 h-67 v-115 h67 Z" fill="{{ $accent }}" />

    {{-- Le W, large, occupe la moitie droite. --}}
    <path d="M580 640 L700 905 L797 700 L893 905 L1035 615" stroke="{{ $accent }}"
          stroke-width="118" stroke-linejoin="miter" stroke-linecap="butt" fill="none" />

    {{-- Le K : hampe verticale, puis un chevron dont la pointe rejoint la hampe.
         Bras court et redresse vers le haut, jambe longue et plus ouverte. --}}
    <rect x="152" y="310" width="120" height="665" rx="22" fill="{{ $ink }}" />
    <path d="M530 540 L272 690 L560 975" stroke="{{ $ink }}" stroke-width="118"
          stroke-linejoin="miter" stroke-linecap="butt" fill="none" />

    {{-- Les trois points qui prolongent l'arc, de plus en plus tenus. --}}
    <circle cx="950" cy="540" r="42" fill="{{ $ink }}" />
    <circle cx="1043" cy="533" r="27" fill="{{ $accent }}" />
    <circle cx="1122" cy="528" r="22" fill="{{ $faded }}" />

    @if ($lockup)
        {{-- Mot-symbole. `textLength` verrouille la largeur : le logo garde ses
             proportions meme si la police de substitution differe. --}}
        <g font-family="'Segoe UI', system-ui, -apple-system, 'Helvetica Neue', Arial, sans-serif">
            <text x="1270" y="580" font-size="300" font-weight="600" letter-spacing="34"
                  textLength="1140" lengthAdjust="spacingAndGlyphs" fill="{{ $ink }}">K<tspan
                  fill="{{ $accent }}">Ɛ</tspan>N<tspan fill="{{ $accent }}">Ɛ</tspan>YA</text>

            <text x="1470" y="790" font-size="150" font-weight="500" letter-spacing="28"
                  textLength="740" lengthAdjust="spacingAndGlyphs" fill="{{ $accent }}">WORKFLOW</text>

            <path d="M1270 742 h140 M2270 742 h140" stroke="{{ $accent }}" stroke-width="12"
                  stroke-linecap="round" />
        </g>
    @endif
</svg>
