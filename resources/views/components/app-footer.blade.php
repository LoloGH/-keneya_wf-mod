{{--
    Pied de page de l'editeur, commun a toutes les interfaces.

    Il vit dans les gabarits (app, portail, salle d'attente), et non dans les
    pages : toute page ajoutee plus tard en herite sans qu'on ait a y penser.

    Volontairement absent des documents imprimes, ticket, ordonnance, recu :
    ce sont des pieces medicales ou comptables, dont l'en-tete est celui de
    l'etablissement, pas celui de l'editeur.
--}}
<footer {{ $attributes->merge(['class' => 'app-footer']) }}>
    <p class="app-footer__produit">
        <strong>{{ config('keneya.name') }}</strong>, un produit d'<a href="https://sukaxess.com"
           target="_blank" rel="noopener">AXESs</a>
        {{-- La version livree, discrete mais lisible : sans elle, impossible de
             dire devant un ecran quel etat du code on a sous les yeux. --}}
        <span class="app-footer__version">v{{ config('keneya.version') }}</span>
    </p>
    <p class="app-footer__rights">
        Tous droits reserves &copy; {{ date('Y') }} AXESs
    </p>
</footer>
