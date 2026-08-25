<?php

namespace App\Actions;

use App\Models\Doctor;
use App\Models\PatientHistory;
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

    public function execute(Visit $visit, Doctor $doctor, string $conclusion): PatientHistory
    {
        $entree = DB::transaction(fn (): PatientHistory => $this->history->record(
            visit: $visit,
            type: PatientHistory::TYPE_CONSULTATION_CONCLUSION,
            description: $conclusion,
            doctor: $doctor,
        ));

        Audit::log(
            Audit::EVENT_CONCLUSION_RECORDED,
            sprintf('Conclusion de consultation redigee par %s.', $doctor->name()),
            $visit,
        );

        return $entree;
    }
}
