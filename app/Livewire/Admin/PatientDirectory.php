<?php

namespace App\Livewire\Admin;

use App\Actions\StoreAttachment;
use App\Livewire\Concerns\NotifiesUser;
use App\Models\Attachment;
use App\Models\Patient;
use App\Models\Service;
use App\Services\PatientTimeline;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Vue globale des patients : l'admin peut ouvrir n'importe quel dossier, sans
 * quitter l'interface /admin. Les passages sont regroupes par visite.
 */
class PatientDirectory extends Component
{
    use NotifiesUser, WithFileUploads, WithPagination;

    public string $search = '';

    public ?int $serviceFilter = null;

    public ?int $openPatientId = null;

    /** Depot d'une piece jointe directement dans le dossier (v3.2, point 3). */
    public bool $addingAttachment = false;

    /** @var array<int, mixed> */
    public array $files = [];

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
        $this->cancelAttachment();
    }

    public function startAttachment(): void
    {
        $this->addingAttachment = true;
        $this->files = [];
        $this->resetValidation();
    }

    public function cancelAttachment(): void
    {
        $this->addingAttachment = false;
        $this->files = [];
        $this->resetValidation();
    }

    /**
     * L'admin depose une piece jointe depuis la vue globale du dossier, sans
     * passer par un renvoi : `patient_id` reste le point d'ancrage, la visite
     * la plus recente n'est renseignee que si elle existe.
     */
    public function saveAttachment(StoreAttachment $action): void
    {
        $this->validate([
            'openPatientId' => ['required', 'integer', 'exists:patients,id'],
            'files' => ['required', 'array', 'min:1', 'max:5'],
            'files.*' => [
                'file',
                'max:'.Attachment::MAX_SIZE_KB,
                'mimes:'.implode(',', Attachment::ALLOWED_EXTENSIONS),
            ],
        ], attributes: ['files' => 'pieces jointes']);

        $patient = Patient::findOrFail($this->openPatientId);
        $visit = $patient->visits()->orderByDesc('opened_at')->first();

        try {
            foreach ($this->files as $file) {
                $action->executeForPatient($file, $patient, Auth::user(), $visit);
            }
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['files' => $e->getMessage()]);
        }

        $this->notifySuccess(sprintf(
            '%d piece(s) jointe(s) ajoutee(s) au dossier de %s.',
            count($this->files),
            $patient->name,
        ));

        $this->cancelAttachment();
    }

    public function render(PatientTimeline $timeline): View
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

        // Meme frise unifiee que cote medecin : les deux roles lisent la meme
        // histoire, dans le meme ordre.
        $frise = $openPatient
            ? $timeline->for($openPatient)
            : ['episodes' => collect(), 'orphans' => collect()];

        return view('livewire.admin.patient-directory', [
            'patients' => $patients,
            'services' => Service::orderBy('name')->get(),
            'openPatient' => $openPatient,
            'episodes' => $frise['episodes'],
            'orphans' => $frise['orphans'],
        ]);
    }
}
