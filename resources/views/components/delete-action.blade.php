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
    <x-icon name="suppression" size="20" />
</button>
