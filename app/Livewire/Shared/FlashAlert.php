<?php

namespace App\Livewire\Shared;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Bandeau de message d'une interface, rendu par Livewire et non par la page.
 *
 * Les messages etaient poses en session et affiches par le gabarit de page :
 * ils n'apparaissaient donc qu'au chargement complet suivant, jamais apres une
 * action Livewire. Un refus de suppression restait invisible — l'administrateur
 * cliquait, et rien ne se passait.
 *
 * Ce composant ecoute un evenement et se re-rend seul. Les composants
 * continuent d'ecrire en session comme avant : le trait NotifiesAdmin emet
 * simplement l'evenement en plus, ce qui evite de reecrire les dizaines
 * d'appels existants.
 */
class FlashAlert extends Component
{
    /** Cle de session lue au premier rendu (redirection, chargement complet). */
    public string $successKey = 'admin.status';

    public string $errorKey = 'admin.error';

    public ?string $message = null;

    /** `success` ou `error`. */
    public string $level = 'success';

    public function mount(string $successKey = 'admin.status', string $errorKey = 'admin.error'): void
    {
        $this->successKey = $successKey;
        $this->errorKey = $errorKey;

        if ($texte = session($errorKey)) {
            $this->show('error', $texte);
        } elseif ($texte = session($successKey)) {
            $this->show('success', $texte);
        }
    }

    #[On('message-affiche')]
    public function show(string $level, string $message): void
    {
        $this->level = $level === 'error' ? 'error' : 'success';
        $this->message = $message;
    }

    public function dismiss(): void
    {
        $this->message = null;
    }

    public function render(): View
    {
        return view('livewire.shared.flash-alert');
    }
}
