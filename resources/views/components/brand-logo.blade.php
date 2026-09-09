{{--
    Logo KƐNƐYA WorkFlow.

    Le logo est fourni par le porteur du projet sous forme d'image ; les
    fichiers d'origine sont conserves dans `public/images/*-source.svg`, et les
    declinaisons servies ici en sont tirees (voir le README, § Logo).

    - variant="color" : bleu nuit et vert, pour les fonds clairs.
    - variant="light" : la meme image declinee pour les fonds sombres, barre de
      navigation, decor de la page de connexion, moniteur de salle d'attente.

    Le mot-symbole (« KƐNƐYA WORKFLOW ») n'apparait qu'avec `lockup` ; ailleurs
    seul le monogramme est repris, pour rester compact.

        <x-brand-logo />                          monogramme couleur
        <x-brand-logo variant="light" />          monogramme clair
        <x-brand-logo lockup variant="color" />   logo complet
        <x-brand-logo svg x="10" y="10" ... />    a l'interieur d'un <svg>

    `svg` est necessaire pour le decor de la page de connexion, ou le logo est
    pose dans une illustration : une balise <img> n'a pas cours a l'interieur
    d'un <svg>, il faut un <image>.
--}}
@props([
    'variant' => 'color',
    'lockup' => false,
    'title' => null,
    'svg' => false,
])

@php
    $clair = $variant === 'light';

    $fichier = match (true) {
        $lockup && $clair => 'images/keneya-logo-clair.png',
        $lockup => 'images/keneya-logo.png',
        $clair => 'images/keneya-icone-claire.png',
        default => 'images/keneya-icone.png',
    };

    // Dimensions reelles des fichiers : donnees au navigateur, elles evitent que
    // la page saute au moment ou l'image arrive.
    [$largeur, $hauteur] = $lockup ? [900, 420] : [512, 405];

    $label = $title ?? config('keneya.name');
@endphp

@if ($svg)
    <image {{ $attributes->merge(['class' => 'brand-logo'.($lockup ? ' brand-logo--lockup' : '')]) }}
           href="{{ asset($fichier) }}" preserveAspectRatio="xMidYMid meet">
        <title>{{ $label }}</title>
    </image>
@else
    <img {{ $attributes->merge(['class' => 'brand-logo'.($lockup ? ' brand-logo--lockup' : '')]) }}
         src="{{ asset($fichier) }}" alt="{{ $label }}"
         width="{{ $largeur }}" height="{{ $hauteur }}" decoding="async">
@endif
