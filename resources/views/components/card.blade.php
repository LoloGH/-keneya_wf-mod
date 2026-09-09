{{-- Carte de contenu : l'unite de mise en page de toute l'application.

     Avant, chaque section ecrivait a la main
     `<section class="card"><h2 class="card__title">...`, et l'en-tete variait
     d'une page a l'autre : parfois un titre seul, parfois un titre suivi d'un
     `<p class="hint">`, parfois rien. La carte est desormais un composant :
     l'en-tete a la meme forme partout, et une page ne peut plus l'inventer.

     Le badge d'icone est ce qui donne son rythme a la page de reference : une
     pastille marine devant chaque titre de bloc, qui rend une longue page
     lisible d'un coup d'oeil.

     Usage :
         <x-card title="Informations generales" icon="batiment"
                 accroche="Ces informations apparaissent sur les documents imprimes.">
             ...
             <x-slot:actions><button ...></x-slot:actions>
         </x-card>

     Sans titre, la carte n'est qu'un cadre : c'est le cas des blocs qui
     portent deja leur propre en-tete. --}}
@props([
    'title' => null,
    'accroche' => null,
    'icon' => null,
    'actions' => null,
    'poll' => null,
])

{{-- `poll` plutot qu'un `wire:poll.{{ ... }}` ecrit par l'appelant : l'intervalle
     fait partie du NOM de l'attribut, et une interpolation dans un nom
     d'attribut ne compile pas sur un composant, dont les attributs sont
     analyses. Une propriete evite ce piege a toutes les files d'attente. --}}
<section {{ $attributes->merge(['class' => 'card']) }}
    @if ($poll) wire:poll.{{ $poll }} @endif>
    @if ($title)
        <header class="card__head">
            <div class="card__heading">
                @if ($icon)
                    <span class="card__badge" aria-hidden="true">
                        <x-icon :name="$icon" size="18" />
                    </span>
                @endif

                <div class="card__intitule">
                    <h2 class="card__title">{{ $title }}</h2>
                    @if ($accroche)
                        <p class="card__accroche">{{ $accroche }}</p>
                    @endif
                </div>
            </div>

            @if ($actions)
                <div class="card__actions">{{ $actions }}</div>
            @endif
        </header>
    @endif

    {{ $slot }}
</section>
