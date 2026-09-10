<?php

namespace App\Actions\Dme;

use App\Actions\Dme\Concerns\ResolvesMedicalRecord;
use App\Models\CareTask;
use App\Models\CareTaskType;
use App\Models\Doctor;
use App\Models\Hospitalization;
use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Support\Carbon;
use Keneya\Dme\Models\CareOrder;

/**
 * Les soins prescrits dans WorkFlow rejoignent le dossier medical (v3.3.2).
 *
 * Une difference de forme separait les deux applications, et c'est elle qui
 * rendait le pont moins evident que pour une ordonnance : WorkFlow resout la
 * recurrence a la creation — « toutes les 8 heures pendant 3 jours » produit
 * neuf lignes, chacune pointable comme faite ou manquee — alors que le dossier
 * medical porte la prescription elle-meme, avec sa frequence et ses bornes.
 *
 * C'est un soin programme au dossier pour une prescription, et les neuf
 * occurrences de WorkFlow y renvoient toutes. L'inverse aurait rempli l'onglet
 * « Soins » de neuf lignes identiques a huit heures d'intervalle, ce qui se lit
 * mal et ne dit rien de plus.
 */
class RecordCareOrder
{
    use ResolvesMedicalRecord;

    public function execute(
        Hospitalization $hospitalization,
        CareTaskType $type,
        Doctor|StaffMember $doctor,
        Carbon $start,
        int $intervalHours,
        int $durationDays,
        ?string $instructions = null,
        ?User $assignedTo = null,
    ): ?CareOrder {
        $patient = $hospitalization->patient;

        if (! $patient) {
            return null;
        }

        $dossier = $this->dossierDuPatient($patient);

        return CareOrder::create([
            'patient_id' => $dossier->getKey(),
            'hospitalization_id' => $hospitalization->dme_hospitalization_id,
            'prescriber_id' => $doctor->user_id,
            'service_id' => $this->serviceDme($hospitalization->service?->name),
            'assigned_nurse_id' => $assignedTo?->getKey(),
            'title' => $type->name,
            'instructions' => $instructions,
            'frequency' => sprintf('Toutes les %d h pendant %d jour(s)', $intervalHours, $durationDays),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addDays($durationDays),
            'status' => 'planned',
        ]);
    }

    /**
     * Le soin programme se cloture quand plus aucune administration n'attend.
     *
     * Tant qu'il en reste une, la prescription court : marquer le dossier
     * « realise » a la premiere injection faite serait faux pour les huit
     * suivantes.
     *
     * Le compte rendu dit ce qui s'est reellement passe, manques compris. Un
     * soin non fait est une information clinique, pas un defaut de saisie a
     * masquer.
     */
    public function syncCompletion(CareTask $task, User $agent): void
    {
        if (! $task->dme_care_order_id) {
            return;
        }

        $occurrences = CareTask::where('dme_care_order_id', $task->dme_care_order_id);

        if ((clone $occurrences)->where('status', CareTask::STATUS_PENDING)->exists()) {
            return;
        }

        $faits = (clone $occurrences)->where('status', CareTask::STATUS_DONE)->count();
        $manques = (clone $occurrences)->where('status', CareTask::STATUS_MISSED)->count();

        CareOrder::whereKey($task->dme_care_order_id)->update([
            'status' => $faits > 0 ? 'completed' : 'refused',
            'completed_at' => now(),
            'completed_by_id' => $agent->getKey(),
            'outcome' => sprintf('%d administration(s) realisee(s), %d manquee(s).', $faits, $manques),
        ]);
    }
}
