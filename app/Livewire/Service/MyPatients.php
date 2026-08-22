<?php

namespace App\Livewire\Service;

use App\Models\Patient;
use App\Models\PatientHistory;
use App\Models\Visit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * « Mes patients » (addendum v2, point 5) : tous les patients que ce medecin a
 * pris en charge, actifs comme clotures, avec recherche par nom ou par
 * `patient_code`.
 *
 * Distinct de la file active : la cloture d'un dossier ne doit jamais filtrer
 * l'acces en lecture a l'historique, seulement retirer le patient de la file.
 */
class MyPatients extends Component
{
    use WithPagination;

    public string $search = '';

    /** Inclure ou non les dossiers deja clotures. */
    public bool $includeClosed = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedIncludeClosed(): void
    {
        $this->resetPage();
    }

    public function showRecord(int $patientId): void
    {
        $this->dispatch('afficher-dossier', patientId: $patientId);
    }

    public function render(): View
    {
        $doctorIds = Auth::user()->doctors()->pluck('id');
        $search = trim($this->search);

        // Les patients ayant au moins une trace de prise en charge par ce medecin.
        $patientIds = PatientHistory::query()
            ->whereIn('doctor_id', $doctorIds)
            ->distinct()
            ->pluck('patient_id');

        $patients = Patient::query()
            ->whereIn('id', $patientIds)
            ->with(['visits' => fn ($query) => $query->with('service')->orderByDesc('opened_at')])
            ->when($search !== '', fn ($query) => $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('patient_code', 'like', "%{$search}%");
            }))
            ->when(! $this->includeClosed, fn ($query) => $query->whereHas(
                'visits',
                fn ($q) => $q->where('status', '!=', Visit::STATUS_CLOSED),
            ))
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.service.my-patients', [
            'patients' => $patients,
        ]);
    }
}
