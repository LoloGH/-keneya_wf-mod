@props([
    'click',
    'label' => 'Supprimer',
    'confirm' => 'Confirmer la suppression ?',
])

{{--
    Action de suppression, presente partout ou une suppression existe.

    Elle n'est jamais masquee, meme quand l'element n'est pas supprimable :
    une action qui disparait sans explication laisse l'administrateur devant
    une case vide, sans savoir si le droit lui manque ou si le bouton n'a
    jamais existe. Le refus vient donc du serveur, avec sa raison — visible
    aussi sur tablette, ou aucune infobulle ne s'affiche au survol.

    Icone seule pour ne pas alourdir des tableaux deja larges ; le libelle
    reste porte par `aria-label` et `title`.
--}}
<button type="button"
        {{ $attributes->merge(['class' => 'btn btn--ghost btn--icon btn--icon-danger']) }}
        wire:click="{{ $click }}"
        wire:confirm="{{ $confirm }}"
        aria-label="{{ $label }}"
        title="{{ $label }}">
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
        <path d="M3 6h18" />
        <path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2" />
        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
        <path d="M10 11v6" />
        <path d="M14 11v6" />
    </svg>
</button>
