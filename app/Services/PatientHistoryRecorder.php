<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\PatientHistory;
use App\Models\Referral;

/**
 * Unique point d'ecriture du journal `patient_history`, qui est append-only :
 * on n'y insere que des lignes, on ne les modifie jamais.
 */
class PatientHistoryRecorder
{
    public function record(
        Patient $patient,
        string $type,
        string $description,
        ?int $serviceId = null,
        ?Doctor $doctor = null,
        ?Referral $referral = null,
    ): PatientHistory {
        return PatientHistory::create([
            'patient_id' => $patient->getKey(),
            'type' => $type,
            'service_id' => $serviceId ?? $patient->service_id,
            'doctor_id' => $doctor?->getKey(),
            'referral_id' => $referral?->getKey(),
            'description' => $description,
        ]);
    }
}
