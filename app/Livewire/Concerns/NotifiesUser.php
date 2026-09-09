<?php

namespace App\Livewire\Concerns;

/**
 * Messages d'une interface : ecrits en session **et** pousses au bandeau.
 *
 * La session couvre le chargement complet (redirection, F5) ; l'evenement
 * couvre l'action Livewire, qui ne re-rend pas le gabarit de page. Sans les
 * deux, un message ne s'affiche que dans un cas sur deux.
 *
 * Ne concerne plus seulement /admin depuis la v3.2.3 : les cles par defaut
 * restent celles de l'administration, mais un composant d'une autre interface
 * passe les siennes, voir Service\Hospitalizations, dont les refus doivent
 * etre lus au moment ou l'on clique.
 */
trait NotifiesUser
{
    protected function notifySuccess(string $message, string $key = 'admin.status'): void
    {
        session()->flash($key, $message);
        $this->dispatch('message-affiche', level: 'success', message: $message);
    }

    protected function notifyError(string $message, string $key = 'admin.error'): void
    {
        session()->flash($key, $message);
        $this->dispatch('message-affiche', level: 'error', message: $message);
    }
}
