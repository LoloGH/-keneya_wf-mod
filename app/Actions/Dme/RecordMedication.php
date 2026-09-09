<?php

namespace App\Actions\Dme;

use App\Actions\Dme\Concerns\ResolvesMedicalRecord;
use App\Models\Doctor;
use App\Models\StaffMember;
use App\Models\Visit;
use App\Support\Audit;
use Keneya\Dme\Models\Medication;

/**
 * Traitement habituel consigne au dossier medical (v3.3.1).
 *
 * A ne pas confondre avec l'ordonnance : celle-ci prescrit pour la fois
 * presente, celui-la note ce que le patient prend deja — un antihypertenseur
 * suivi depuis des annees, un traitement commence ailleurs. Le module tient
 * les deux dans des tables distinctes, et c'est la bonne separation : on ne
 * represcrit pas un traitement au long cours a chaque venue.
 *
 * Un traitement ne se supprime pas, il s'arrete ou se suspend. Savoir qu'un
 * patient a pris quelque chose puis l'a arrete est une information clinique.
 */
class RecordMedication
{
    use ResolvesMedicalRecord;

    /** @var array<string, string> */
    public const STATUSES = [
        'active' => 'En cours',
        'suspended' => 'Suspendu',
        'stopped' => 'Arrete',
    ];

    /** @var array<string, string> */
    public const ROUTES = [
        'orale' => 'Orale',
        'iv' => 'Intraveineuse',
        'im' => 'Intramusculaire',
        'cutanee' => 'Cutanee',
        'autre' => 'Autre',
    ];

    /**
     * @param  array{
     *     name: string,
     *     dosage?: ?string,
     *     frequency?: ?string,
     *     route?: ?string,
     *     started_on?: ?string,
     *     comment?: ?string,
     * }  $data
     */
    public function execute(Visit $visit, Doctor|StaffMember $doctor, array $data): Medication
    {
        $dossier = $this->dossierMedical($visit);

        $traitement = $dossier->medications()->create([
            'name' => $data['name'],
            'dosage' => $data['dosage'] ?: null,
            'frequency' => $data['frequency'] ?: null,
            'route' => $data['route'] ?: null,
            'started_on' => $data['started_on'] ?: null,
            'status' => 'active',
            'comment' => $data['comment'] ?: null,
            'prescriber_id' => $doctor->user_id,
        ]);

        Audit::log(
            Audit::EVENT_MEDICATION_RECORDED,
            sprintf(
                'Traitement « %s » consigne au dossier de %s par %s.',
                $data['name'],
                $visit->patient->patient_code,
                $doctor->name(),
            ),
            $visit,
        );

        return $traitement;
    }

    /**
     * Suspend ou arrete un traitement. La ligne demeure : un traitement
     * interrompu explique souvent la venue suivante.
     */
    public function updateStatus(Visit $visit, Doctor|StaffMember $doctor, Medication $traitement, string $statut): Medication
    {
        abort_unless(
            (int) $traitement->patient_id === (int) $this->dossierMedical($visit)->getKey(),
            404,
        );

        $traitement->update([
            'status' => $statut,
            'ended_on' => $statut === 'stopped' ? now()->toDateString() : null,
        ]);

        Audit::log(
            Audit::EVENT_MEDICATION_RECORDED,
            sprintf(
                'Traitement « %s » marque %s au dossier de %s par %s.',
                $traitement->name,
                mb_strtolower(self::STATUSES[$statut] ?? $statut),
                $visit->patient->patient_code,
                $doctor->name(),
            ),
            $visit,
        );

        return $traitement->refresh();
    }
}
