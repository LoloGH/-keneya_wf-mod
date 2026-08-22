<?php

namespace App\Livewire\Service;

use App\Models\Patient;
use App\Models\PatientHistory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Dossier / historique du patient, affiche dans un panneau lateral de
 * l'interface /service.
 *
 * Ce n'est volontairement pas une page separee : le medecin ne doit jamais
 * quitter son interface unique pour consulter un dossier.
 */
class PatientRecordPanel extends Component
{
    public ?int $patientId = null;

    /**
     * Ouvert par la file d'attente ou les panneaux de renvoi.
     */
    #[On('afficher-dossier')]
    public function open(int $patientId): void
    {
        $this->patientId = $patientId;
    }

    public function close(): void
    {
        $this->patientId = null;
    }

    public function render(): View
    {
        $patient = $this->patientId
            ? Patient::with('service')->find($this->patientId)
            : null;

        $history = $patient
            ? PatientHistory::query()
                ->with(['service', 'doctor.user', 'referral'])
                ->where('patient_id', $patient->getKey())
                ->orderBy('id')
                ->get()
            : collect();

        return view('livewire.service.patient-record-panel', [
            'patient' => $patient,
            'history' => $history,
        ]);
    }
}
