<?php

namespace App\Actions\Dme;

use App\Actions\Dme\Concerns\ResolvesMedicalRecord;
use App\Models\Doctor;
use App\Models\PatientHistory;
use App\Models\Visit;
use App\Services\PatientHistoryRecorder;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Keneya\Dme\Models\Prescription;

/**
 * Ordonnance de fin de consultation, ecrite dans le dossier medical (v3.3.1).
 *
 * C'est la fusion decidee au §4 de la note du chantier : la forme vient du
 * DME — ordonnance numerotee, lignes structurees, statut, rattachement a une
 * consultation — et la fonction vient de WorkFlow, qui sait apposer la
 * signature du prescripteur et les tampons sur le PDF.
 *
 * Une seule table, donc, et les deux interfaces y ecrivent. WorkFlow garde son
 * ecran « Fin de consultation » ; ce qu'il enregistrait dans sa propre table
 * `prescriptions` part desormais dans `dme_prescriptions`.
 *
 * La ligne d'historique du parcours porte maintenant l'identifiant de
 * l'ordonnance : la frise n'a plus a l'apparier par rang, ce que son propre
 * commentaire signalait comme un pis-aller.
 */
class CreateMedicalPrescription
{
    use ResolvesMedicalRecord;

    public function __construct(private readonly PatientHistoryRecorder $history) {}

    /**
     * Duree de validite par defaut d'une ordonnance, en jours.
     *
     * Trois mois : c'est l'usage pour un traitement courant, et cela evite
     * qu'une ordonnance sans date de fin soit presentee des annees plus tard
     * comme si elle valait encore.
     */
    private const VALIDITE_JOURS = 90;

    /**
     * @param  array<int, array{medicament?: ?string, posologie?: ?string, duree?: ?string}>  $lignes
     */
    public function execute(Visit $visit, Doctor $doctor, array $lignes): Prescription
    {
        $retenues = $this->nettoie($lignes);

        if ($retenues === []) {
            throw new InvalidArgumentException('Une ordonnance comporte au moins un medicament.');
        }

        $dossier = $this->dossierMedical($visit);

        return DB::transaction(function () use ($visit, $doctor, $dossier, $retenues): Prescription {
            $ordonnance = $dossier->prescriptions()->create([
                'doctor_id' => $doctor->user_id,
                'issued_on' => now()->toDateString(),
                'valid_until' => now()->addDays(self::VALIDITE_JOURS)->toDateString(),
                // « Validee » d'emblee : dans cet hopital, le medecin qui
                // ecrit l'ordonnance est celui qui l'engage. Le circuit de
                // validation separee du module suppose un pharmacien, qui
                // n'existe pas ici.
                'status' => 'validated',
                'validated_by' => $doctor->user_id,
                'validated_at' => now(),
            ]);

            foreach ($retenues as $rang => $ligne) {
                $ordonnance->items()->create([
                    'position' => $rang + 1,
                    'medication_name' => $ligne['medicament'],
                    'frequency' => $ligne['posologie'] ?: null,
                    'duration' => $ligne['duree'] ?: null,
                ]);
            }

            Audit::log(
                Audit::EVENT_PRESCRIPTION_CREATED,
                sprintf('Ordonnance %s etablie par %s.', $ordonnance->prescription_number, $doctor->name()),
                $visit,
                ['ordonnance' => $ordonnance->prescription_number],
            );

            $this->history->record(
                visit: $visit,
                type: PatientHistory::TYPE_PRESCRIPTION,
                description: sprintf('Ordonnance %s etablie par %s.', $ordonnance->prescription_number, $doctor->name()),
                doctor: $doctor,
                dmePrescriptionId: $ordonnance->getKey(),
            );

            return $ordonnance;
        });
    }

    /**
     * Ecarte les lignes sans medicament et normalise les espaces.
     *
     * Le medecin peut avoir ouvert une ligne de plus sans la remplir : une
     * ordonnance ne doit pas porter de ligne vide.
     *
     * @param  array<int, array{medicament?: ?string, posologie?: ?string, duree?: ?string}>  $lignes
     * @return array<int, array{medicament: string, posologie: string, duree: string}>
     */
    private function nettoie(array $lignes): array
    {
        $retenues = [];

        foreach ($lignes as $ligne) {
            $medicament = trim((string) ($ligne['medicament'] ?? ''));

            if ($medicament === '') {
                continue;
            }

            $retenues[] = [
                'medicament' => $medicament,
                'posologie' => trim((string) ($ligne['posologie'] ?? '')),
                'duree' => trim((string) ($ligne['duree'] ?? '')),
            ];
        }

        return array_values($retenues);
    }
}
