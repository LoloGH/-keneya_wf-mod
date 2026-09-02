{{-- En-tete de page : fil d'ariane, titre, sous-titre.

     Le fil d'ariane est purement indicatif dans ce produit : une interface ne
     mene jamais vers une autre — un role, une interface. Les etapes ne sont
     donc pas des liens, et il ne faut pas leur en donner l'apparence : ce
     serait promettre une navigation qui n'existe pas.

     Usage :
         <x-page-header
             :fil="['Accueil', 'Etablissement']"
             titre="Informations de l'etablissement"
             sous-titre="Ces informations apparaitront sur toutes les ordonnances et tickets imprimes." /> --}}
@props([
    'fil' => [],
    'titre',
    'sousTitre' => null,
])

<header {{ $attributes->merge(['class' => 'page-header']) }}>
    @if ($fil !== [])
        <nav class="fil" aria-label="Vous etes ici">
            <ol class="fil__liste">
                @foreach ($fil as $etape)
                    <li class="fil__etape @if ($loop->last) fil__etape--courante @endif"
                        @if ($loop->last) aria-current="page" @endif>
                        @unless ($loop->first)
                            <x-icon name="chevron" size="14" class="fil__separateur" />
                        @endunless
                        <span>{{ $etape }}</span>
                    </li>
                @endforeach
            </ol>
        </nav>
    @endif

    <h1 class="page-header__titre">{{ $titre }}</h1>

    @if ($sousTitre)
        <p class="page-header__sous-titre">{{ $sousTitre }}</p>
    @endif
</header>
