<?php

namespace App\Livewire\Service;

use App\Actions\ScheduleAppointment;
use App\Actions\SendPortalLink;
use App\Actions\StoreAttachment;
use App\Livewire\Concerns\RequiresCapability;
use App\Models\Attachment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\PatientHistory;
use App\Models\StaffMember;
use App\Models\StaffType;
use App\Models\Visit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
    use RequiresCapability, WithFileUploads, WithPagination;

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
        $this->assertCapability(StaffType::CAP_SCHEDULE_APPOINTMENT);

        $this->validate([
            'appointmentPatientId' => ['required', 'integer', 'exists:patients,id'],
            'appointmentAt' => ['required', 'date', 'after:now'],
        ], attributes: ['appointmentAt' => 'date du rendez-vous']);

        $agent = $this->agent();
        $patient = Patient::findOrFail($this->appointmentPatientId);

        // Le rendez-vous s'ancre sur le dernier passage connu du patient.
        $visit = $patient->visits()->orderByDesc('opened_at')->first()
            ?? new Visit(['patient_id' => $patient->getKey(), 'service_id' => $agent->service_id]);

        try {
            $action->execute($visit, $agent, Carbon::parse($this->appointmentAt), $agent->service_id);
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

    /**
     * Le rattachement du compte connecte : sa fiche medecin, ou a defaut sa
     * fiche de personnel generique (v3.3.1). Les deux portent un service et un
     * compte, ce qui suffit ici.
     */
    private function agent(): Doctor|StaffMember
    {
        $user = Auth::user();

        $agent = $user->doctors()->orderBy('id')->first() ?? $user->staffMember;

        abort_unless($agent, 403, "Aucun service n'est rattache a votre compte.");

        return $agent;
    }

    /**
     * Les patients dont ce compte a signe au moins un acte, quel que soit son
     * rattachement. Sans aucun rattachement, la liste est vide plutot que
     * complete : un filtre absent montrerait tout l'hopital.
     */
    private function mesPatientIds(): Collection
    {
        $user = Auth::user();
        $doctorIds = $user->doctors()->pluck('id');
        $staffMemberId = $user->staffMember?->getKey();

        if ($doctorIds->isEmpty() && $staffMemberId === null) {
            return collect();
        }

        return PatientHistory::query()
            ->where(function ($requete) use ($doctorIds, $staffMemberId) {
                if ($doctorIds->isNotEmpty()) {
                    $requete->orWhereIn('doctor_id', $doctorIds);
                }

                if ($staffMemberId !== null) {
                    $requete->orWhere('staff_member_id', $staffMemberId);
                }
            })
            ->distinct()
            ->pluck('patient_id');
    }

    /**
     * Envoi du lien « mes documents » par SMS (v3.2, point 7), sur demande
     * explicite du medecin : jamais automatiquement.
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
        $search = trim($this->search);

        // Les patients ayant au moins une trace de prise en charge par ce compte.
        $patientIds = $this->mesPatientIds();

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
            // Calculee une fois pour la liste entiere : la capacite ne depend
            // pas du patient, et la relire a chaque ligne interrogerait le
            // type de personnel autant de fois qu'il y a de patients.
            'peutOuvrirLeDme' => Auth::user()->hasCapability(StaffType::CAP_ACCESS_DME),
        ]);
    }
}
