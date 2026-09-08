<?php

namespace App\Actions\Dme\Concerns;

use App\Models\Visit;
use Keneya\Dme\Models\Patient as DossierMedical;
use Keneya\Dme\Patients\PatientIdentifierResolver;

/**
 * Le dossier medical du patient d'un passage, cree au besoin.
 *
 * Chaque formulaire du v3.3.1 commence par la meme question — quel dossier du
 * DME correspond a ce patient de WorkFlow ? — et elle n'admet qu'une reponse :
 * la table d'identifiants externes du module, jamais un rapprochement sur le
 * nom. Ce trait tient cette reponse en un seul endroit, pour qu'elle ne se
 * mette pas a diverger d'un formulaire a l'autre.
 *
 * Effet de bord voulu : un patient consulte pour la premiere fois obtient son
 * dossier medical au premier acte, sans que personne ait a y penser.
 */
trait ResolvesMedicalRecord
{
    protected function dossierMedical(Visit $visit): DossierMedical
    {
        $patient = $visit->patient;

        return app(PatientIdentifierResolver::class)->resolve(
            system: 'keneya_workflow',
            value: (string) $patient->patient_code,
            attributes: [
                'name' => $patient->name,
                'sex' => $patient->gender,
                'age' => $patient->age,
                'phone' => $patient->mobile,
                'label' => 'Dossier KEneYa WorkFlow',
            ],
        );
    }
}
