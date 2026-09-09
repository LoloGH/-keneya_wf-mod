<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Garde-fou de capacite, cote serveur (v3.2.2).
 *
 * Masquer une section suffit a la retirer de la vue, jamais a la rendre
 * inatteignable : un composant Livewire s'appelle sans passer par le menu. Les
 * actions dont la capacite peut etre decochee le verifient donc ici, sur le
 * type du compte connecte : la meme lecture que celle qui pilote l'affichage,
 * pas une seconde regle a tenir a jour.
 */
trait RequiresCapability
{
    protected function assertCapability(string $capability): void
    {
        abort_unless(
            (bool) Auth::user()?->hasCapability($capability),
            403,
            'Cette action ne releve pas de votre fonction.',
        );
    }
}
