<?php

namespace App\Livewire\Admin;

use App\Models\Patient;
use App\Models\PatientHistory;
use App\Models\Service;
use App\Models\Visit;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Vue globale des patients : l'admin peut ouvrir n'importe quel dossier, sans
 * quitter l'interface /admin. Les passages sont regroupes par visite.
 */
class PatientDirectory extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $serviceFilter = null;

    public ?int $openPatientId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedServiceFilter(): void
    {
        $this->resetPage();
    }

    public function openRecord(int $patientId): void
    {
        $this->openPatientId = $patientId;
    }

    public function closeRecord(): void
    {
        $this->openPatientId = null;
    }

    public function render(): View
    {
        $search = trim($this->search);

        $patients = Patient::query()
            ->with(['latestVisit.service'])
            ->when($search !== '', fn ($query) => $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('patient_code', 'like', "%{$search}%")
                    ->orWhere('crno', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%");
            }))
            ->when($this->serviceFilter, fn ($query) => $query->whereHas(
                'visits',
                fn ($q) => $q->where('service_id', $this->serviceFilter),
            ))
            ->orderByDesc('id')
            ->paginate(15);

        $openPatient = $this->openPatientId
            ? Patient::with(['visits.service', 'companions'])->find($this->openPatientId)
            : null;

        $history = $openPatient
            ? PatientHistory::with(['service', 'doctor.user'])
                ->where('patient_id', $openPatient->getKey())
                ->orderBy('id')
                ->get()
            : collect();

        $episodes = $openPatient
            ? $openPatient->visits->sortByDesc('opened_at')->values()->map(fn (Visit $visit) => [
                'visit' => $visit,
                'entries' => $history->where('visit_id', $visit->getKey())->values(),
            ])
            : collect();

        return view('livewire.admin.patient-directory', [
            'patients' => $patients,
            'services' => Service::orderBy('name')->get(),
            'openPatient' => $openPatient,
            'episodes' => $episodes,
            'orphans' => $history->whereNull('visit_id')->values(),
        ]);
    }
}
