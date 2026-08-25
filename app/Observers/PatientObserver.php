<?php

namespace App\Observers;

use App\Models\Patient;
use App\Services\PatientCodeGenerator;
use Illuminate\Support\Str;

class PatientObserver
{
    public function __construct(private readonly PatientCodeGenerator $codes) {}

    /**
     * Le patient_code, le code d'acces et le jeton du portail sont attribues
     * ici, et nulle part ailleurs : quel que soit le point d'entree
     * (formulaire, seeder, import, tinker), un patient ne peut pas exister
     * sans identifiant unique ni sans moyen d'acceder a ses documents.
     */
    public function creating(Patient $patient): void
    {
        if (blank($patient->patient_code)) {
            $patient->patient_code = $this->codes->forPatient();
        }

        if (blank($patient->access_code)) {
            // Quatre chiffres, zeros de tete conserves : « 0421 » est valide.
            $patient->access_code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        }

        if (blank($patient->portal_token)) {
            // Un UUID, jamais le patient_code ni l'id : l'URL du portail ne
            // doit pas se deviner de proche en proche.
            $patient->portal_token = (string) Str::uuid();
        }
    }
}
