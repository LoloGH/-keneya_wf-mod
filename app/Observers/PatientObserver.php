<?php

namespace App\Observers;

use App\Models\Patient;
use App\Services\PatientCodeGenerator;

class PatientObserver
{
    public function __construct(private readonly PatientCodeGenerator $codes) {}

    /**
     * Le patient_code est attribue ici, et nulle part ailleurs : quel que soit le
     * point d'entree (formulaire, seeder, import, tinker), un patient ne peut pas
     * exister sans identifiant unique.
     */
    public function creating(Patient $patient): void
    {
        if (blank($patient->patient_code)) {
            $patient->patient_code = $this->codes->forPatient();
        }
    }
}
