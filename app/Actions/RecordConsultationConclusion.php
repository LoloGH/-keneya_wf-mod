<?php

namespace App\Actions;

use App\Models\Doctor;
use App\Models\PatientHistory;
use App\Models\StaffMember;
use App\Models\Visit;
use App\Services\PatientHistoryRecorder;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Conclusion de consultation (v3.2, point 5).
 *
 * Distincte de l'ordonnance, qui reste dediee aux medicaments : ici le medecin
 * consigne ce qu'il retient de la prise en charge. Une ligne d'historique
 * plutot qu'une table, puisque cela appartient au parcours du patient.
 */
class RecordConsultationConclusion
{
    public function __construct(private readonly PatientHistoryRecorder $history) {}

    /**
     * `$pathologyId` est toujours facultatif (v3.2.9, point 1) : il sert a
     * regrouper des patients pour une diffusion ulterieure, et ne doit jamais
     * conditionner l'enregistrement d'une conclusion.
     */
    public function execute(Visit $visit, Doctor|StaffMember $doctor, string $conclusion, ?int $pathologyId = null): PatientHistory
    {
        $entree = DB::transaction(function () use ($visit, $doctor, $conclusion, $pathologyId): PatientHistory {
            if ($pathologyId !== null) {
                $visit->update(['pathology_id' => $pathologyId]);
            }

            return $this->history->record(
                visit: $visit,
                type: PatientHistory::TYPE_CONSULTATION_CONCLUSION,
                description: $conclusion,
                doctor: $doctor,
            );
        });

        Audit::log(
            Audit::EVENT_CONCLUSION_RECORDED,
            sprintf('Conclusion de consultation redigee par %s.', $doctor->name()),
            $visit,
        );

        return $entree;
    }
}
