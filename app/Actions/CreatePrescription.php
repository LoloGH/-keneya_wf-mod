<?php

namespace App\Actions;

use App\Models\Doctor;
use App\Models\PatientHistory;
use App\Models\Prescription;
use App\Models\Visit;
use App\Services\PatientHistoryRecorder;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Ordonnance de fin de consultation. Texte libre pour cette version.
 */
class CreatePrescription
{
    public function __construct(private readonly PatientHistoryRecorder $history) {}

    public function execute(Visit $visit, Doctor $doctor, string $content): Prescription
    {
        return DB::transaction(function () use ($visit, $doctor, $content): Prescription {
            $prescription = Prescription::create([
                'patient_id' => $visit->patient_id,
                'visit_id' => $visit->getKey(),
                'doctor_id' => $doctor->getKey(),
                'content' => $content,
            ]);

            Audit::log(
                Audit::EVENT_PRESCRIPTION_CREATED,
                sprintf('Ordonnance etablie par %s.', $doctor->name()),
                $prescription,
            );

            $this->history->record(
                visit: $visit,
                type: PatientHistory::TYPE_PRESCRIPTION,
                description: sprintf('Ordonnance etablie par %s.', $doctor->name()),
                doctor: $doctor,
            );

            return $prescription;
        });
    }
}
