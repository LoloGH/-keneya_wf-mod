{{-- Bandeau d'information — le bloc « Bon a savoir » de la reference.

     A distinguer de `.alert`, qui annonce le resultat d'une action que
     l'utilisateur vient de declencher et disparait ensuite. Un bandeau
     d'information, lui, est permanent : il explique ce que fait l'ecran, et il
     est la avant qu'on ait rien fait.

     Trois tons, et pas un de plus :
       - `info`   (defaut) : sarcelle, ce qu'il faut savoir ;
       - `alerte` : ambre, une consequence a peser avant d'agir ;
       - `blocage`: rouge, ce qui empeche l'ecran de fonctionner.

     Usage :
         <x-notice title="Bon a savoir">
             Le tampon et la signature apparaitront en bas de chaque ordonnance.
         </x-notice>

         <x-notice ton="blocage">Aucun tampon enregistre.</x-notice> --}}
@props([
    'title' => null,
    'ton' => 'info',
])

@php
    $icone = match ($ton) {
        'alerte' => 'alerte',
        'blocage' => 'alerte',
        default => 'valide',
    };
@endphp

<aside {{ $attributes->merge(['class' => 'notice notice--'.$ton]) }} role="note">
    <span class="notice__icone" aria-hidden="true">
        <x-icon :name="$icone" size="18" />
    </span>

    <div class="notice__corps">
        @if ($title)
            <p class="notice__titre">{{ $title }}</p>
        @endif
        <div class="notice__texte">{{ $slot }}</div>
    </div>
</aside>
