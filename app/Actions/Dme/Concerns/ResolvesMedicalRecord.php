<?php

namespace App\Actions\Dme\Concerns;

use App\Models\Visit;
use App\Support\Dme\PatientProjection;
use Keneya\Dme\Models\Patient as DossierMedical;

/**
 * Le dossier medical du patient d'un passage, cree au besoin.
 *
 * Chaque formulaire du v3.3.1 commence par la meme question, quel dossier du
 * DME correspond a ce patient de WorkFlow ? La reponse, et la forme des champs
 * projetes, vivent dans {@see PatientProjection} : ce trait n'est que le
 * raccourci qui la rend disponible depuis une action, a partir d'un passage.
 */
trait ResolvesMedicalRecord
{
    protected function dossierMedical(Visit $visit): DossierMedical
    {
        return PatientProjection::resolve($visit->patient);
    }
}
