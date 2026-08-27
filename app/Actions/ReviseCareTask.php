<?php

namespace App\Actions;

use App\Models\CareTask;
use App\Models\CareTaskType;
use App\Models\Doctor;
use App\Models\PatientHistory;
use App\Models\StaffType;
use App\Models\User;
use App\Services\PatientHistoryRecorder;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Correction et annulation d'un soin programme (v3.2.3, point 4).
 *
 * Une prescription se corrige : mauvais dosage saisi, mauvaise heure, soin
 * arrete parce que l'etat du patient a change. Jusqu'ici la seule issue etait
 * de laisser la ligne fausse au dossier, ou de la supprimer en base — c'est-a-
 * dire de reecrire l'histoire du patient sans laisser de trace.
 *
 * Ici rien n'est efface. Une correction est journalisee avec l'avant et
 * l'apres ; une annulation pose un statut « annule », qui sort le soin de tous
 * les comptes tout en le laissant visible au dossier, avec son motif et son
 * auteur.
 */
class ReviseCareTask
{
    public function __construct(private readonly PatientHistoryRecorder $history) {}

    /**
     * Corriger le contenu d'un soin : type, instructions, heure, assignation.
     *
     * @param  array<string, mixed>  $changes
     */
    public function revise(CareTask $task, User $user, array $changes): CareTask
    {
        $this->assertMayRevise($task, $user);

        if ($task->isCancelled()) {
            throw new InvalidArgumentException('Ce soin est annule : il ne peut plus etre modifie.');
        }

        $avant = $this->snapshot($task);

        $attributs = array_filter([
            'care_task_type_id' => $changes['care_task_type_id'] ?? null,
            'instructions' => array_key_exists('instructions', $changes) ? $changes['instructions'] : null,
            'scheduled_at' => isset($changes['scheduled_at']) ? Carbon::parse($changes['scheduled_at']) : null,
            'assigned_to_user_id' => $changes['assigned_to_user_id'] ?? null,
        ], fn ($valeur, $cle) => array_key_exists($cle, $changes), ARRAY_FILTER_USE_BOTH);

        if ($attributs === []) {
            return $task;
        }

        $task->update($attributs);
        $task->refresh()->load('type');

        $apres = $this->snapshot($task);

        if ($avant === $apres) {
            // Rien n'a bouge : pas de ligne d'audit qui laisserait croire a une
            // correction qui n'a pas eu lieu.
            return $task;
        }

        $this->trace($task, $user, Audit::EVENT_CARE_TASK_REVISED, sprintf(
            '« %s » corrige par %s. Avant : %s. Apres : %s.',
            $task->type->name,
            $user->name,
            $avant,
            $apres,
        ));

        return $task;
    }

    /**
     * Annuler un soin, quel que soit son statut.
     *
     * Un soin deja marque comme administre peut l'avoir ete par erreur :
     * l'annuler est la seule facon de corriger le compte sans effacer la ligne.
     */
    public function cancel(CareTask $task, User $user, string $reason): CareTask
    {
        $this->assertMayRevise($task, $user);

        if ($task->isCancelled()) {
            throw new InvalidArgumentException('Ce soin est deja annule.');
        }

        $reason = trim($reason);

        if ($reason === '') {
            // Une annulation sans motif lisible ne vaut pas mieux qu'une
            // suppression silencieuse.
            throw new InvalidArgumentException("Indiquez le motif de l'annulation.");
        }

        $statutPrecedent = $task->statusLabel();

        DB::transaction(function () use ($task, $user, $reason): void {
            $task->update([
                'status' => CareTask::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $user->getKey(),
                'cancellation_reason' => $reason,
            ]);
        });

        $task->refresh()->load('type');

        $this->trace($task, $user, Audit::EVENT_CARE_TASK_CANCELLED, sprintf(
            '« %s » du %s annule par %s (etait : %s). Motif : %s',
            $task->type->name,
            $task->scheduled_at->format('d/m/Y H:i'),
            $user->name,
            $statutPrecedent,
            $reason,
        ));

        return $task;
    }

    /**
     * Qui peut corriger ou annuler : le medecin qui a prescrit, ou un medecin
     * du meme service porteur de la capacite d'hospitalisation — celui qui
     * tient le service en son absence.
     *
     * Meme regle que partout ailleurs : l'acces se lit sur les capacites du
     * type de personnel, pas sur une liste de roles ecrite ici.
     */
    private function assertMayRevise(CareTask $task, User $user): void
    {
        $hospitalization = $task->hospitalization()->firstOrFail();

        if (! $hospitalization->isActive()) {
            throw new InvalidArgumentException('Cette hospitalisation est cloturee : ses soins ne bougent plus.');
        }

        $prescripteur = Doctor::find($task->prescribed_by_doctor_id);

        if ($prescripteur && (int) $prescripteur->user_id === (int) $user->getKey()) {
            return;
        }

        $tientLeService = $user->hasCapability(StaffType::CAP_ADMIT_HOSPITALIZATION)
            && $user->doctorFor($hospitalization->service_id) !== null;

        if (! $tientLeService) {
            throw new InvalidArgumentException(
                'Seul le medecin prescripteur, ou un medecin du service en charge des hospitalisations, peut corriger ou annuler ce soin.'
            );
        }
    }

    /**
     * Trace double, comme pour tout acte metier : le journal d'audit pour
     * l'etablissement, le dossier du patient pour la continuite des soins.
     */
    private function trace(CareTask $task, User $user, string $event, string $description): void
    {
        $hospitalization = $task->hospitalization()->with('visit')->firstOrFail();

        if ($visit = $hospitalization->visit) {
            $this->history->record(
                visit: $visit,
                type: $event === Audit::EVENT_CARE_TASK_CANCELLED
                    ? PatientHistory::TYPE_CARE_TASK_CANCELLED
                    : PatientHistory::TYPE_CARE_TASK_REVISED,
                description: $description,
                serviceId: $hospitalization->service_id,
                doctor: $user->doctorFor($hospitalization->service_id) ?? $user->staffMember,
            );
        }

        Audit::log($event, $description, $task);
    }

    /**
     * Etat lisible d'un soin, pour dire ce qui a change sans afficher des
     * identifiants a personne.
     */
    private function snapshot(CareTask $task): string
    {
        return sprintf(
            '%s le %s%s%s',
            $task->type?->name ?? CareTaskType::find($task->care_task_type_id)?->name ?? '—',
            $task->scheduled_at->format('d/m/Y H:i'),
            filled($task->instructions) ? ', '.$task->instructions : '',
            $task->assigned_to_user_id
                ? ', confie a '.(User::find($task->assigned_to_user_id)?->name ?? '—')
                : '',
        );
    }
}
