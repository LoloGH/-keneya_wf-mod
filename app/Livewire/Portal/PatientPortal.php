<?php

namespace App\Livewire\Portal;

use App\Actions\GrantPortalAccess;
use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Portail patient (v3.2, point 7).
 *
 * Lecture seule, publique, protegee par un code a quatre chiffres. Rien n'est
 * charge ni affiche tant que le code n'a pas ete valide : la page ne divulgue
 * meme pas le nom du patient avant.
 */
class PatientPortal extends Component
{
    public string $token = '';

    public string $code = '';

    public ?string $error = null;

    public function mount(string $token): void
    {
        $this->token = $token;
    }

    /**
     * L'acces accorde est retenu en session, pas dans une propriete du
     * composant : une propriete Livewire voyage avec le client.
     */
    public function unlock(GrantPortalAccess $action): void
    {
        $this->validate(
            ['code' => ['required', 'digits:4']],
            attributes: ['code' => 'code d\'acces'],
        );

        $patient = $this->patient();

        $resultat = $action->attempt($patient, $this->code);

        $this->code = '';

        if (! $resultat['granted']) {
            $this->error = $resultat['message'];

            return;
        }

        $this->error = null;
        session()->put('portal.'.$patient->getKey(), true);
    }

    public function lock(): void
    {
        session()->forget('portal.'.$this->patient()->getKey());
        $this->error = null;
    }

    private function patient(): Patient
    {
        return Patient::where('portal_token', $this->token)->firstOrFail();
    }

    public function render(): View
    {
        $patient = $this->patient();
        $unlocked = session()->get('portal.'.$patient->getKey()) === true;

        return view('livewire.portal.patient-portal', [
            'patient' => $patient,
            'unlocked' => $unlocked,
            'attachments' => $unlocked
                ? $patient->attachments()->orderByDesc('id')->get()
                : collect(),
            // Les ordonnances viennent du dossier medical depuis la v3.3.1 :
            // le patient lit celle que son medecin a signee, pas une copie.
            'prescriptions' => $unlocked ? $patient->ordonnances() : collect(),
            'appointments' => $unlocked
                ? $patient->appointments()
                    ->with(['service', 'doctor.user'])
                    ->where('scheduled_at', '>=', now())
                    ->where('status', Appointment::STATUS_SCHEDULED)
                    ->orderBy('scheduled_at')
                    ->get()
                : collect(),
        ]);
    }
}
