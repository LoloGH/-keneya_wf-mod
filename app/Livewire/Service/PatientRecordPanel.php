<?php

namespace App\Livewire\Service;

use App\Models\Patient;
use App\Services\PatientTimeline;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Dossier du patient, affiche dans un panneau lateral de l'interface /service.
 *
 * Ce n'est volontairement pas une page separee : le medecin ne doit jamais
 * quitter son interface unique pour consulter un dossier.
 *
 * L'historique est regroupe par visite : un patient deja venu doit laisser
 * voir son episode precedent distinctement du nouveau, sous le meme
 * `patient_code`. Un dossier cloture reste integralement lisible — la cloture
 * ne masque que la file d'attente, jamais la lecture.
 */
class PatientRecordPanel extends Component
{
    public ?int $patientId = null;

    #[On('afficher-dossier')]
    public function open(int $patientId): void
    {
        $this->patientId = $patientId;
    }

    public function close(): void
    {
        $this->patientId = null;
    }

    public function render(PatientTimeline $timeline): View
    {
        $patient = $this->patientId
            ? Patient::with(['companions', 'visits.service'])->find($this->patientId)
            : null;

        // Une seule frise par passage : consultations, renvois, ordonnances,
        // conclusions, paiements et pieces jointes melanges par ordre de date.
        $frise = $patient
            ? $timeline->for($patient)
            : ['episodes' => collect(), 'orphans' => collect()];

        return view('livewire.service.patient-record-panel', [
            'patient' => $patient,
            'episodes' => $frise['episodes'],
            'orphans' => $frise['orphans'],
        ]);
    }
}
