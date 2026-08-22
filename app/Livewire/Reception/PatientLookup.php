<?php

namespace App\Livewire\Reception;

use App\Actions\OpenNewEpisode;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Visit;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Recherche prealable a l'enregistrement (addendum v3).
 *
 * Un patient deja connu ne doit jamais recevoir un second `patient_code` :
 * on retrouve son identite, la receptionniste la confirme visuellement — pour
 * eviter toute confusion entre homonymes — puis on ouvre un nouvel episode
 * sous le meme identifiant.
 */
class PatientLookup extends Component
{
    public string $search = '';

    /** Patient retenu, en attente de confirmation par la receptionniste. */
    public ?int $selectedPatientId = null;

    public ?int $serviceId = null;

    public string $reason = '';

    public function updatedSearch(): void
    {
        $this->selectedPatientId = null;
        $this->resetValidation();
    }

    public function select(int $patientId): void
    {
        $this->selectedPatientId = $patientId;
        $this->serviceId = null;
        $this->reason = '';
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->reset(['selectedPatientId', 'serviceId', 'reason']);
        $this->resetValidation();
    }

    public function openEpisode(OpenNewEpisode $action): void
    {
        $this->validate([
            'selectedPatientId' => ['required', 'integer', 'exists:patients,id'],
            'serviceId' => ['required', 'integer', 'exists:services,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], attributes: [
            'selectedPatientId' => 'patient',
            'serviceId' => 'service',
            'reason' => 'motif',
        ]);

        $patient = Patient::findOrFail($this->selectedPatientId);

        $visit = $action->execute($patient, (int) $this->serviceId, $this->reason ?: null);

        session()->flash('reception.success', sprintf(
            'Nouvel episode ouvert pour %s (dossier %s) — ticket n° %d au service %s.',
            $patient->name,
            $patient->patient_code,
            $visit->token,
            $visit->service->name,
        ));

        $this->reset(['search', 'selectedPatientId', 'serviceId', 'reason']);
        $this->dispatch('patient-enregistre');
    }

    public function render(): View
    {
        $search = trim($this->search);

        $matches = $search === '' ? collect() : Patient::query()
            ->where(function ($query) use ($search) {
                $query->where('patient_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->limit(10)
            ->get();

        return view('livewire.reception.patient-lookup', [
            'matches' => $matches,
            'selected' => $this->selectedPatientId ? Patient::with('visits.service')->find($this->selectedPatientId) : null,
            'services' => Service::orderBy('name')->get(),
            'openStatuses' => [Visit::STATUS_WAITING, Visit::STATUS_CALLED],
        ]);
    }
}
