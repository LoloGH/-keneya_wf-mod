<?php

namespace App\Actions;

use App\Models\Pathology;
use App\Models\Visit;
use App\Services\BroadcastRecipients;
use App\Support\Audit;

/**
 * La pathologie notee sur un passage (v3.3.1).
 *
 * Elle etait jusqu'ici saisie avec la conclusion de consultation, dans le meme
 * formulaire. La conclusion est passee au dossier medical, ou est sa place :
 * c'est une donnee de sante. La pathologie, elle, reste ici, parce qu'elle ne
 * sert pas au soin mais au fonctionnement de l'etablissement — s'adresser plus
 * tard a un groupe de patients par SMS ({@see BroadcastRecipients}).
 *
 * Elle serait partie avec la conclusion si l'on n'y avait pas pris garde, et
 * la diffusion aurait perdu sa seule source, sans que rien ne le signale.
 *
 * Facultative, et elle ne conditionne rien : un passage sans pathologie notee
 * est un passage ordinaire, pas un dossier incomplet.
 */
class AssignVisitPathology
{
    public function execute(Visit $visit, ?int $pathologyId): Visit
    {
        if ((int) $visit->pathology_id === (int) $pathologyId) {
            return $visit;
        }

        $visit->update(['pathology_id' => $pathologyId]);

        Audit::log(
            Audit::EVENT_CONCLUSION_RECORDED,
            sprintf(
                'Pathologie du passage de %s : %s.',
                $visit->patient->patient_code,
                $pathologyId
                    ? (Pathology::find($pathologyId)?->name ?? 'inconnue')
                    : 'retiree',
            ),
            $visit,
        );

        return $visit->fresh();
    }
}
