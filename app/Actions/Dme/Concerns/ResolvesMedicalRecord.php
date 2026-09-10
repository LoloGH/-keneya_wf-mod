<?php

namespace App\Actions\Dme\Concerns;

use App\Models\Patient;
use App\Models\Visit;
use App\Support\Dme\PatientProjection;
use Keneya\Dme\Models\Patient as DossierMedical;
use Keneya\Dme\Models\Service as ServiceDme;

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
        return $this->dossierDuPatient($visit->patient);
    }

    /**
     * Le meme dossier, quand l'action ne part pas d'un passage : une sortie
     * d'hospitalisation ou une prescription de soins connaissent le patient
     * sans passer par sa visite du jour.
     */
    protected function dossierDuPatient(Patient $patient): DossierMedical
    {
        return PatientProjection::resolve($patient);
    }

    /**
     * Le service du DME correspondant a celui de WorkFlow, s'il existe.
     *
     * Rapprochement par le nom, et jamais de creation : les deux applications
     * tiennent chacune leur liste de services, et fabriquer ici un service du
     * DME au vu d'un nom rendrait la correspondance encore plus incertaine.
     * A defaut, l'acte reste sans service : c'est une information de moins,
     * pas une information fausse.
     */
    protected function serviceDme(?string $nom): ?int
    {
        if (blank($nom)) {
            return null;
        }

        return ServiceDme::where('name', $nom)->value('id');
    }
}
