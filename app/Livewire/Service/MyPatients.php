<?php

namespace App\Livewire\Service;

use App\Actions\ScheduleAppointment;
use App\Actions\SendPortalLink;
use App\Actions\StoreAttachment;
use App\Models\Attachment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\PatientHistory;
use App\Models\Visit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
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
    use WithFileUploads, WithPagination;

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

    /** Patient pour lequel on fixe un rendez-vous. */
    public ?int $appointmentPatientId = null;

    public string $appointmentAt = '';

    /** Patient auquel on ajoute une piece jointe. */
    public ?int $attachmentPatientId = null;

    /** @var array<int, TemporaryUploadedFile> */
    public array $files = [];

    public function showRecord(int $patientId): void
    {
        $this->dispatch('afficher-dossier', patientId: $patientId);
    }

    // ------------------------------------------------------ Rendez-vous

    /**
     * Un rendez-vous peut desormais etre fixe pour n'importe quel patient de la
     * liste, pas seulement celui en cours de consultation.
     */
    public function startAppointment(int $patientId): void
    {
        $this->appointmentPatientId = $patientId;
        $this->appointmentAt = '';
        $this->attachmentPatientId = null;
        $this->resetValidation();
    }

    public function cancelAppointment(): void
    {
        $this->reset(['appointmentPatientId', 'appointmentAt']);
        $this->resetValidation();
    }

    public function saveAppointment(ScheduleAppointment $action): void
    {
        $this->validate([
            'appointmentPatientId' => ['required', 'integer', 'exists:patients,id'],
            'appointmentAt' => ['required', 'date', 'after:now'],
        ], attributes: ['appointmentAt' => 'date du rendez-vous']);

        $doctor = $this->doctor();
        $patient = Patient::findOrFail($this->appointmentPatientId);

        // Le rendez-vous s'ancre sur le dernier passage connu du patient.
        $visit = $patient->visits()->orderByDesc('opened_at')->first()
            ?? new Visit(['patient_id' => $patient->getKey(), 'service_id' => $doctor->service_id]);

        try {
            $action->execute($visit, $doctor, Carbon::parse($this->appointmentAt), $doctor->service_id);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['appointmentAt' => $e->getMessage()]);
        }

        session()->flash('service.status', sprintf('Rendez-vous fixe pour %s.', $patient->name));

        $this->cancelAppointment();
        $this->dispatch('rendez-vous-cree');
    }

    // ---------------------------------------------------- Pieces jointes

    /**
     * Une piece jointe peut etre deposee directement depuis le dossier, sans
     * passer par un renvoi.
     */
    public function startAttachment(int $patientId): void
    {
        $this->attachmentPatientId = $patientId;
        $this->files = [];
        $this->appointmentPatientId = null;
        $this->resetValidation();
    }

    public function cancelAttachment(): void
    {
        $this->reset(['attachmentPatientId', 'files']);
        $this->resetValidation();
    }

    public function saveAttachment(StoreAttachment $action): void
    {
        $this->validate([
            'attachmentPatientId' => ['required', 'integer', 'exists:patients,id'],
            'files' => ['required', 'array', 'min:1', 'max:5'],
            'files.*' => [
                'file',
                'max:'.Attachment::MAX_SIZE_KB,
                'mimes:'.implode(',', Attachment::ALLOWED_EXTENSIONS),
            ],
        ], attributes: ['files' => 'pieces jointes']);

        $patient = Patient::findOrFail($this->attachmentPatientId);
        $visit = $patient->visits()->orderByDesc('opened_at')->first();

        try {
            foreach ($this->files as $file) {
                $action->executeForPatient($file, $patient, Auth::user(), $visit);
            }
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['files' => $e->getMessage()]);
        }

        session()->flash('service.status', sprintf(
            '%d piece(s) jointe(s) ajoutee(s) au dossier de %s.',
            count($this->files),
            $patient->name,
        ));

        $this->cancelAttachment();
        $this->dispatch('afficher-dossier', patientId: $patient->getKey());
    }

    private function doctor(): Doctor
    {
        return Auth::user()->doctors()->orderBy('id')->firstOrFail();
    }

    /**
     * Envoi du lien « mes documents » par SMS (v3.2, point 7), sur demande
     * explicite du medecin — jamais automatiquement.
     */
    public function sendPortalLink(int $patientId, SendPortalLink $action): void
    {
        $patient = Patient::findOrFail($patientId);

        try {
            $action->execute($patient);
        } catch (InvalidArgumentException $e) {
            session()->flash('service.error', $e->getMessage());

            return;
        }

        session()->flash('service.status', sprintf(
            'Lien de documents envoye a %s.',
            $patient->mobile,
        ));
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
