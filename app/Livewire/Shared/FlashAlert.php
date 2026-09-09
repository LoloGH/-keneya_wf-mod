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
 * action Livewire. Un refus de suppression restait invisible : l'administrateur
 * cliquait, et rien ne se passait.
 *
 * Ce composant ecoute un evenement et se re-rend seul. Les composants
 * continuent d'ecrire en session comme avant : le trait NotifiesUser emet
 * simplement l'evenement en plus, ce qui evite de reecrire les dizaines
 * d'appels existants.
 */
class FlashAlert extends Component
{
    /**
     * Cles de session lues au premier rendu (redirection, chargement complet).
     *
     * Plusieurs cles plutot qu'une seule : depuis la v3.3.1, /staff/{slug}
     * affiche des ecrans partages avec l'interface medecin, qui ecrivent sous
     * `service.*`. Sans cela, un enregistrement reussi depuis un poste dedie
     * ne disait rien a l'ecran.
     *
     * @var array<int, string>
     */
    public array $successKeys = ['admin.status'];

    /** @var array<int, string> */
    public array $errorKeys = ['admin.error'];

    public ?string $message = null;

    /** `success` ou `error`. */
    public string $level = 'success';

    /**
     * @param  string|array<int, string>  $successKey
     * @param  string|array<int, string>  $errorKey
     */
    public function mount(string|array $successKey = 'admin.status', string|array $errorKey = 'admin.error'): void
    {
        $this->successKeys = (array) $successKey;
        $this->errorKeys = (array) $errorKey;

        // L'erreur d'abord : quand les deux sont posees, c'est le refus qui
        // doit se lire, pas le succes qui l'a precede.
        foreach ($this->errorKeys as $cle) {
            if ($texte = session($cle)) {
                $this->show('error', $texte);

                return;
            }
        }

        foreach ($this->successKeys as $cle) {
            if ($texte = session($cle)) {
                $this->show('success', $texte);

                return;
            }
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
