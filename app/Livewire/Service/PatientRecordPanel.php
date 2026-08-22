<?php

namespace App\Livewire\Service;

use App\Models\Patient;
use App\Models\PatientHistory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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

    public function render(): View
    {
        $patient = $this->patientId
            ? Patient::with(['companions', 'visits.service'])->find($this->patientId)
            : null;

        /** @var Collection<int, PatientHistory> $history */
        $history = $patient
            ? PatientHistory::query()
                ->with(['service', 'doctor.user', 'referral', 'attachments'])
                ->where('patient_id', $patient->getKey())
                ->orderBy('id')
                ->get()
            : collect();

        // Un groupe par visite, du passage le plus recent au plus ancien.
        $episodes = $patient
            ? $patient->visits->sortByDesc('opened_at')->values()->map(fn ($visit) => [
                'visit' => $visit,
                'entries' => $history->where('visit_id', $visit->getKey())->values(),
            ])
            : collect();

        // Les lignes anterieures a l'introduction des visites, le cas echeant.
        $orphans = $history->whereNull('visit_id')->values();

        return view('livewire.service.patient-record-panel', [
            'patient' => $patient,
            'episodes' => $episodes,
            'orphans' => $orphans,
        ]);
    }
}
