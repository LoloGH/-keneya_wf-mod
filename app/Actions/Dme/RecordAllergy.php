<?php

namespace App\Actions\Dme;

use App\Actions\Dme\Concerns\ResolvesMedicalRecord;
use App\Models\Doctor;
use App\Models\StaffMember;
use App\Models\Visit;
use App\Support\Audit;
use Keneya\Dme\Models\Allergy;

/**
 * Allergie consignee au dossier medical depuis /service (v3.3.1).
 *
 * De tous les formulaires du chantier, c'est celui dont la donnee est la plus
 * lourde de consequences : le module lit cette table au moment de prescrire
 * pour signaler un conflit medicamenteux (§22). Une allergie oubliee ici ne
 * declenche aucune alerte la-bas.
 *
 * D'ou deux choix : la severite est demandee des la saisie plutot que laissee
 * a « inconnue », et une allergie ne se supprime pas, elle se refute. Le
 * dossier garde ainsi la trace qu'elle a ete evoquee, et pourquoi elle a ete
 * ecartee, au lieu de faire disparaitre l'information.
 */
class RecordAllergy
{
    use ResolvesMedicalRecord;

    /**
     * @param  array{
     *     allergen: string,
     *     allergen_type?: ?string,
     *     reaction?: ?string,
     *     severity: string,
     *     comment?: ?string,
     * }  $data
     */
    public function execute(Visit $visit, Doctor|StaffMember $doctor, array $data): Allergy
    {
        $dossier = $this->dossierMedical($visit);

        $allergie = $dossier->allergies()->create([
            'allergen' => $data['allergen'],
            'allergen_type' => $data['allergen_type'] ?: null,
            'reaction' => $data['reaction'] ?: null,
            'severity' => $data['severity'],
            'observed_on' => now()->toDateString(),
            'status' => 'active',
            'comment' => $data['comment'] ?: null,
            'recorded_by' => $doctor->user_id,
        ]);

        Audit::log(
            Audit::EVENT_ALLERGY_RECORDED,
            sprintf(
                'Allergie « %s » (%s) consignee au dossier de %s par %s.',
                $data['allergen'],
                mb_strtolower(Allergy::SEVERITIES[$data['severity']] ?? $data['severity']),
                $visit->patient->patient_code,
                $doctor->name(),
            ),
            $visit,
        );

        return $allergie;
    }

    /**
     * Change le statut d'une allergie : resolue, ou refutee.
     *
     * Jamais de suppression. Une allergie evoquee puis ecartee est une
     * information clinique en elle-meme : l'effacer reviendrait a laisser le
     * prochain medecin refaire le meme cheminement.
     */
    public function updateStatus(Visit $visit, Doctor|StaffMember $doctor, Allergy $allergie, string $statut): Allergy
    {
        abort_unless(
            (int) $allergie->patient_id === (int) $this->dossierMedical($visit)->getKey(),
            404,
        );

        $allergie->update(['status' => $statut]);

        Audit::log(
            Audit::EVENT_ALLERGY_RECORDED,
            sprintf(
                'Allergie « %s » marquee %s au dossier de %s par %s.',
                $allergie->allergen,
                $statut === 'refuted' ? 'refutee' : 'resolue',
                $visit->patient->patient_code,
                $doctor->name(),
            ),
            $visit,
        );

        return $allergie->refresh();
    }
}
