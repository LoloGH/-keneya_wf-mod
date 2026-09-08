<?php

namespace App\Actions\Dme;

use App\Actions\Dme\Concerns\ResolvesMedicalRecord;
use App\Models\Doctor;
use App\Models\Visit;
use App\Support\Audit;
use Keneya\Dme\Models\MedicalHistory;

/**
 * Antecedent consigne au dossier medical depuis /service (v3.3.1).
 *
 * Le module tient les cinq categories dans une table unique — personnels,
 * chirurgicaux, familiaux, gyneco-obstetriques, facteurs de risque — et les
 * champs qui n'ont de sens que pour l'une d'elles restent nuls pour les
 * autres : le lien de parente pour un antecedent familial, l'etablissement
 * pour un antecedent chirurgical.
 *
 * Un antecedent appartient au patient, pas au passage : il n'est rattache a
 * aucune consultation, et vaut pour toute la vie du dossier.
 */
class RecordMedicalHistory
{
    use ResolvesMedicalRecord;

    /**
     * @param  array{
     *     category: string,
     *     label: string,
     *     year?: ?string,
     *     relative?: ?string,
     *     facility?: ?string,
     *     comment?: ?string,
     * }  $data
     */
    public function execute(Visit $visit, Doctor $doctor, array $data): MedicalHistory
    {
        $dossier = $this->dossierMedical($visit);

        $antecedent = $dossier->medicalHistories()->create([
            'category' => $data['category'],
            'label' => $data['label'],
            'year' => $data['year'] ?: null,
            // Le lien de parente n'a de sens que pour un antecedent familial,
            // l'etablissement que pour un antecedent chirurgical. Les garder
            // pour les autres categories remplirait le dossier de valeurs
            // heritees d'une saisie precedente.
            'relative' => $data['category'] === 'family' ? ($data['relative'] ?: null) : null,
            'facility' => $data['category'] === 'surgical' ? ($data['facility'] ?: null) : null,
            'comment' => $data['comment'] ?: null,
            'recorded_by' => $doctor->user_id,
        ]);

        Audit::log(
            Audit::EVENT_MEDICAL_BACKGROUND,
            sprintf(
                'Antecedent %s consigne au dossier de %s par %s.',
                mb_strtolower(MedicalHistory::CATEGORIES[$data['category']] ?? $data['category']),
                $visit->patient->patient_code,
                $doctor->name(),
            ),
            $visit,
        );

        return $antecedent;
    }
}
