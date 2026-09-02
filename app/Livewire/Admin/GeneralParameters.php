<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\NotifiesUser;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Parametres generaux (refonte visuelle, groupe « Systeme »).
 *
 * Cette section existe pour une raison precise : le delai de rappel de
 * rendez-vous est lu a chaque passage du planificateur depuis la v3.2.3, mais
 * n'etait modifiable nulle part. Il fallait une intervention en base pour le
 * changer, sur un reglage que l'etablissement est le mieux place pour decider.
 *
 * Les autres reglages de l'application ne sont volontairement pas rapatries
 * ici : le delai d'invitation des visiteurs se regle dans « Retours et
 * incidents », l'acte qui vaut ticket dans « Tarifs ». Un reglage se modifie la
 * ou l'on voit ses effets ; les rassembler dans une page fourre-tout les
 * eloignerait de ce qu'ils gouvernent. La page les recense malgre tout, pour
 * qu'un administrateur qui les cherche sache ou aller.
 */
class GeneralParameters extends Component
{
    use NotifiesUser;

    public int $reminderMinutes = Setting::DEFAULT_APPOINTMENT_REMINDER_MINUTES;

    public function mount(): void
    {
        $this->reminderMinutes = (int) Setting::get(
            Setting::APPOINTMENT_REMINDER_MINUTES,
            (string) Setting::DEFAULT_APPOINTMENT_REMINDER_MINUTES,
        );
    }

    public function save(): void
    {
        $this->validate([
            // Cinq minutes au moins : en deca, le rappel partirait alors que le
            // patient est deja en route. Une journee au plus : au-dela, ce
            // n'est plus un rappel, c'est une convocation.
            'reminderMinutes' => ['required', 'integer', 'min:5', 'max:1440'],
        ], attributes: ['reminderMinutes' => 'delai de rappel']);

        Setting::put(Setting::APPOINTMENT_REMINDER_MINUTES, (string) $this->reminderMinutes);

        $this->notifySuccess('Delai de rappel des rendez-vous mis a jour.');
    }

    public function render(): View
    {
        return view('livewire.admin.general-parameters');
    }
}
