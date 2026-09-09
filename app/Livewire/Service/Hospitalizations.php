<?php

namespace App\Livewire\Service;

use App\Actions\AdmitPatient;
use App\Actions\DischargePatient;
use App\Actions\PrescribeCareTasks;
use App\Actions\ReviseCareTask;
use App\Livewire\Concerns\NotifiesUser;
use App\Livewire\Concerns\RequiresCapability;
use App\Livewire\Service\Concerns\ScopedToOwnService;
use App\Models\CareTask;
use App\Models\CareTaskType;
use App\Models\Hospitalization;
use App\Models\Room;
use App\Models\StaffType;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Patients hospitalises du service (v3.2.1, point 11).
 *
 * Section distincte de la file d'attente et de « Mes patients » : un patient
 * hospitalise n'attend pas un tour, il occupe un lit.
 */
class Hospitalizations extends Component
{
    use NotifiesUser, RequiresCapability, ScopedToOwnService;

    /** Visite en cours d'admission. */
    public ?int $admittingVisitId = null;

    public ?int $roomId = null;

    /** L'admission au-dela de la capacite demande une confirmation explicite. */
    public bool $overCapacityConfirmed = false;

    /** Hospitalisation pour laquelle on prescrit des soins. */
    public ?int $prescribingId = null;

    public ?int $careTaskTypeId = null;

    public string $careInstructions = '';

    public string $careStartsAt = '';

    public int $careIntervalHours = 8;

    public int $careDurationDays = 3;

    /**
     * Assignation nommee, facultative : le medecin peut designer quelqu'un dans
     * un cas precis. Ce n'est jamais une restriction d'acces — voir
     * StaffCareTasks — mais une priorite d'affichage.
     */
    public ?int $careAssignedToUserId = null;

    /** Hospitalisation dont on deplie la liste des soins programmes. */
    public ?int $viewingCareTasksFor = null;

    /** Hospitalisation dont on deplie les notes de releve. */
    public ?int $viewingHandoffFor = null;

    /** Soin en cours de correction. */
    public ?int $revisingTaskId = null;

    public ?int $reviseTypeId = null;

    public string $reviseInstructions = '';

    public string $reviseScheduledAt = '';

    public ?int $reviseAssignedToUserId = null;

    /** Soin en cours d'annulation, et son motif — jamais facultatif. */
    public ?int $cancellingTaskId = null;

    public string $cancellationReason = '';

    /**
     * La section entiere est optionnelle : le composant refuse de se monter
     * si le type du compte ne porte pas la capacite.
     */
    /**
     * L'ecran sert deux capacites depuis la v3.3.1 : admettre un patient, et
     * lui prescrire des soins. Elles se cochent separement — ce ne sont pas la
     * meme decision — d'ou une porte d'entree qui accepte l'une ou l'autre.
     *
     * Chaque action garde la sienne : `admit()` exige l'hospitalisation,
     * `prescribe()` la prescription. Ouvrir l'ecran n'accorde rien.
     */
    public function mount(int $serviceId): void
    {
        abort_unless(
            auth()->user()?->hasCapability(StaffType::CAP_ADMIT_HOSPITALIZATION)
                || auth()->user()?->hasCapability(StaffType::CAP_PRESCRIBE_CARE),
            403,
            'Cette action ne releve pas de votre fonction.',
        );

        $this->serviceId = $this->assertOwnService($serviceId);
    }

    #[On('service-change')]
    public function handleServiceChange(int $serviceId): void
    {
        $this->onServiceChanged($serviceId);
    }

    protected function resetServiceState(): void
    {
        $this->cancelAdmission();
        $this->cancelPrescription();
        $this->hideCareTasks();
        $this->reset(['viewingHandoffFor']);
    }

    // ------------------------------------------------------------ Admission

    public function startAdmission(int $visitId): void
    {
        $this->admittingVisitId = $visitId;
        $this->roomId = null;
        $this->overCapacityConfirmed = false;
        $this->resetValidation();
    }

    public function cancelAdmission(): void
    {
        $this->reset(['admittingVisitId', 'roomId', 'overCapacityConfirmed']);
        $this->resetValidation();
    }

    public function admit(AdmitPatient $action): void
    {
        $this->assertCapability(StaffType::CAP_ADMIT_HOSPITALIZATION);

        $this->validate([
            'admittingVisitId' => ['required', 'integer', 'exists:visits,id'],
            'roomId' => ['nullable', 'integer', 'exists:rooms,id'],
        ], attributes: ['admittingVisitId' => 'patient', 'roomId' => 'salle']);

        $visit = Visit::where('service_id', $this->serviceId)->findOrFail($this->admittingVisitId);
        $room = $this->roomId ? Room::findOrFail($this->roomId) : null;

        try {
            $action->execute($visit, $this->currentAgent(), $room, $this->overCapacityConfirmed);
        } catch (InvalidArgumentException $e) {
            // Salle pleine : on n'interdit pas, on demande confirmation.
            throw ValidationException::withMessages(['roomId' => $e->getMessage()]);
        }

        $this->notifySuccess(sprintf('%s hospitalise.', $visit->patient->name), 'service.status');

        $this->cancelAdmission();
        $this->dispatch('file-mise-a-jour');
    }

    // ------------------------------------------------------- Soins prescrits

    public function startPrescription(int $hospitalizationId): void
    {
        $this->prescribingId = $hospitalizationId;
        $this->careTaskTypeId = null;
        $this->careInstructions = '';
        $this->careStartsAt = now()->addHour()->startOfHour()->format('Y-m-d\TH:i');
        $this->careIntervalHours = 8;
        $this->careDurationDays = 3;
        $this->careAssignedToUserId = null;
        $this->resetValidation();
    }

    public function cancelPrescription(): void
    {
        $this->reset(['prescribingId', 'careTaskTypeId', 'careInstructions', 'careStartsAt', 'careAssignedToUserId']);
        $this->resetValidation();
    }

    public function prescribe(PrescribeCareTasks $action): void
    {
        // Masquer le bouton ne suffit pas : un composant Livewire s'appelle
        // sans passer par l'ecran.
        $this->assertCapability(StaffType::CAP_PRESCRIBE_CARE);

        $this->validate([
            'prescribingId' => ['required', 'integer', 'exists:hospitalizations,id'],
            'careTaskTypeId' => ['required', 'integer', 'exists:care_task_types,id'],
            'careInstructions' => ['nullable', 'string', 'max:2000'],
            'careStartsAt' => ['required', 'date'],
            'careIntervalHours' => ['required', 'integer', 'min:1', 'max:168'],
            'careDurationDays' => ['required', 'integer', 'min:1', 'max:60'],
            'careAssignedToUserId' => ['nullable', 'integer', 'exists:users,id'],
        ], attributes: [
            'careTaskTypeId' => 'type de soin',
            'careStartsAt' => 'debut',
            'careIntervalHours' => 'intervalle',
            'careDurationDays' => 'duree',
        ]);

        $hospitalization = Hospitalization::where('service_id', $this->serviceId)
            ->findOrFail($this->prescribingId);

        try {
            $crees = $action->execute(
                hospitalization: $hospitalization,
                type: CareTaskType::findOrFail($this->careTaskTypeId),
                doctor: $this->currentAgent(),
                start: Carbon::parse($this->careStartsAt),
                intervalHours: $this->careIntervalHours,
                durationDays: $this->careDurationDays,
                instructions: $this->careInstructions ?: null,
                assignedTo: $this->careAssignedToUserId
                    ? User::find($this->careAssignedToUserId)
                    : null,
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['careIntervalHours' => $e->getMessage()]);
        }

        $this->notifySuccess(sprintf('%d administration(s) programmee(s).', $crees), 'service.status');

        $this->cancelPrescription();
    }

    // ------------------------------------- Correction et annulation d'un soin

    public function showCareTasks(int $hospitalizationId): void
    {
        $this->viewingCareTasksFor = $this->viewingCareTasksFor === $hospitalizationId
            ? null
            : $hospitalizationId;

        $this->closeCareTaskForms();
    }

    public function hideCareTasks(): void
    {
        $this->reset(['viewingCareTasksFor']);
        $this->closeCareTaskForms();
    }

    /**
     * Les notes de releve d'un sejour. Volontairement separees des soins : on
     * les consulte a la prise de poste, pas en marquant une administration.
     */
    public function showHandoff(int $hospitalizationId): void
    {
        $this->viewingHandoffFor = $this->viewingHandoffFor === $hospitalizationId
            ? null
            : $hospitalizationId;
    }

    public function startRevision(int $taskId): void
    {
        $task = $this->careTaskInMyService($taskId);

        $this->cancellingTaskId = null;
        $this->revisingTaskId = $task->getKey();
        $this->reviseTypeId = $task->care_task_type_id;
        $this->reviseInstructions = (string) $task->instructions;
        $this->reviseScheduledAt = $task->scheduled_at->format('Y-m-d\TH:i');
        $this->reviseAssignedToUserId = $task->assigned_to_user_id;
        $this->resetValidation();
    }

    public function saveRevision(ReviseCareTask $action): void
    {
        $this->validate([
            'revisingTaskId' => ['required', 'integer', 'exists:care_tasks,id'],
            'reviseTypeId' => ['required', 'integer', 'exists:care_task_types,id'],
            'reviseInstructions' => ['nullable', 'string', 'max:2000'],
            'reviseScheduledAt' => ['required', 'date'],
            'reviseAssignedToUserId' => ['nullable', 'integer', 'exists:users,id'],
        ], attributes: [
            'reviseTypeId' => 'type de soin',
            'reviseScheduledAt' => 'heure',
        ]);

        $task = $this->careTaskInMyService($this->revisingTaskId);

        try {
            $action->revise($task, Auth::user(), [
                'care_task_type_id' => $this->reviseTypeId,
                'instructions' => $this->reviseInstructions ?: null,
                'scheduled_at' => $this->reviseScheduledAt,
                'assigned_to_user_id' => $this->reviseAssignedToUserId,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->notifyError($e->getMessage(), 'service.error');

            return;
        }

        $this->notifySuccess('Soin corrige.', 'service.status');

        $this->closeCareTaskForms();
    }

    public function startCancellation(int $taskId): void
    {
        $this->revisingTaskId = null;
        $this->cancellingTaskId = $this->careTaskInMyService($taskId)->getKey();
        $this->cancellationReason = '';
        $this->resetValidation();
    }

    public function confirmCancellation(ReviseCareTask $action): void
    {
        $this->validate([
            'cancellingTaskId' => ['required', 'integer', 'exists:care_tasks,id'],
            'cancellationReason' => ['required', 'string', 'max:255'],
        ], attributes: ['cancellationReason' => "motif de l'annulation"]);

        $task = $this->careTaskInMyService($this->cancellingTaskId);

        try {
            $action->cancel($task, Auth::user(), $this->cancellationReason);
        } catch (InvalidArgumentException $e) {
            $this->notifyError($e->getMessage(), 'service.error');

            return;
        }

        $this->notifySuccess('Soin annule : il reste au dossier, il ne compte plus.', 'service.status');

        $this->closeCareTaskForms();
    }

    public function closeCareTaskForms(): void
    {
        $this->reset([
            'revisingTaskId', 'reviseTypeId', 'reviseInstructions',
            'reviseScheduledAt', 'reviseAssignedToUserId',
            'cancellingTaskId', 'cancellationReason',
        ]);
        $this->resetValidation();
    }

    /**
     * Un soin d'un autre service n'existe pas de mon point de vue : le
     * cloisonnement precede le controle d'acces porte par l'action.
     */
    private function careTaskInMyService(int $taskId): CareTask
    {
        return CareTask::with(['type', 'hospitalization'])
            ->whereHas('hospitalization', fn ($q) => $q->where('service_id', $this->serviceId))
            ->findOrFail($taskId);
    }

    // ---------------------------------------------------------------- Sortie

    public function discharge(int $hospitalizationId, DischargePatient $action): void
    {
        // Faire sortir un patient releve de qui l'a admis, pas de qui prescrit.
        $this->assertCapability(StaffType::CAP_ADMIT_HOSPITALIZATION);

        $hospitalization = Hospitalization::where('service_id', $this->serviceId)
            ->findOrFail($hospitalizationId);

        try {
            $action->execute($hospitalization, $this->currentAgent());
        } catch (InvalidArgumentException $e) {
            $this->notifyError($e->getMessage(), 'service.error');

            return;
        }

        $this->notifySuccess('Sortie d\'hospitalisation enregistree.', 'service.status');
    }

    public function render(): View
    {
        return view('livewire.service.hospitalizations', [
            'hospitalizations' => Hospitalization::query()
                ->with(['patient', 'room', 'admittedByDoctor.user', 'admittedByStaffMember.user'])
                ->withCount([
                    'careTasks as pending_care_tasks_count' => fn ($q) => $q->where('status', CareTask::STATUS_PENDING),
                ])
                ->where('service_id', $this->serviceId)
                ->active()
                ->orderByDesc('admitted_at')
                ->get(),
            // Les patients appeles, candidats a une admission.
            'callable' => Visit::query()
                ->with('patient')
                ->inTodaysQueue($this->serviceId)
                ->where('status', Visit::STATUS_CALLED)
                ->orderBy('token')
                ->get(),
            'rooms' => Room::where('service_id', $this->serviceId)
                ->withCount(['hospitalizations as occupancy_count' => fn ($q) => $q->where('status', Hospitalization::STATUS_ACTIVE)])
                ->orderBy('name')
                ->get(),
            'careTaskTypes' => CareTaskType::orderBy('name')->get(),
            // Prescrire un soin est un acte attribuable depuis la v3.3.1 :
            // sa propre capacite, distincte d'admettre et d'executer. Un
            // compte qui ne la porte pas ne voit pas le bouton — mieux vaut
            // ne pas le montrer que le refuser au clic.
            'peutPrescrireDesSoins' => auth()->user()->hasCapability(StaffType::CAP_PRESCRIBE_CARE),
            // Admettre et faire sortir relevent de l'autre capacite : un
            // compte qui ne fait que prescrire lit l'ecran sans y admettre.
            'peutHospitaliser' => auth()->user()->hasCapability(StaffType::CAP_ADMIT_HOSPITALIZATION),
            // Les soins du sejour deplie : la liste complete, annules compris,
            // parce que le dossier garde tout — c'est le decompte qui les
            // ignore, pas l'affichage.
            'careTasks' => $this->viewingCareTasksFor
                ? CareTask::with(['type', 'assignedTo', 'completedBy', 'cancelledBy'])
                    ->where('hospitalization_id', $this->viewingCareTasksFor)
                    ->orderBy('scheduled_at')
                    ->get()
                : collect(),
            // Le personnel du service capable d'executer un soin : de quoi
            // designer quelqu'un nommement, sans y etre oblige.
            'carers' => User::query()
                ->whereHas('staffMember', fn ($q) => $q->where('service_id', $this->serviceId))
                ->orderBy('name')
                ->get()
                ->filter(fn (User $user) => (bool) $user->staffType()?->can(StaffType::CAP_CARE_TASKS))
                ->values(),
        ]);
    }
}
