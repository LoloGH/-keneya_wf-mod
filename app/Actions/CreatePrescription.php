<?php

namespace App\Actions;

use App\Models\Doctor;
use App\Models\PatientHistory;
use App\Models\Prescription;
use App\Models\Visit;
use App\Services\PatientHistoryRecorder;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Ordonnance de fin de consultation, ecrite ligne par ligne (v3.2.6).
 *
 * Chaque ligne porte un medicament, et facultativement sa posologie et sa
 * duree. Les lignes sans medicament sont ecartees : le medecin peut avoir
 * ouvert une ligne de plus sans la remplir.
 */
class CreatePrescription
{
    public function __construct(private readonly PatientHistoryRecorder $history) {}

    /**
     * @param  array<int, array{medicament?: ?string, posologie?: ?string, duree?: ?string}>  $lines
     */
    public function execute(Visit $visit, Doctor $doctor, array $lines): Prescription
    {
        $lignes = $this->nettoie($lines);

        if ($lignes === []) {
            throw new InvalidArgumentException('Une ordonnance comporte au moins un medicament.');
        }

        return DB::transaction(function () use ($visit, $doctor, $lignes): Prescription {
            $prescription = Prescription::create([
                'patient_id' => $visit->patient_id,
                'visit_id' => $visit->getKey(),
                'doctor_id' => $doctor->getKey(),
                'lines' => $lignes,
            ]);

            Audit::log(
                Audit::EVENT_PRESCRIPTION_CREATED,
                sprintf('Ordonnance etablie par %s.', $doctor->name()),
                $prescription,
            );

            $this->history->record(
                visit: $visit,
                type: PatientHistory::TYPE_PRESCRIPTION,
                description: sprintf('Ordonnance etablie par %s.', $doctor->name()),
                doctor: $doctor,
            );

            return $prescription;
        });
    }

    /**
     * Ecarte les lignes vides et normalise les trois champs.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array{medicament: string, posologie: ?string, duree: ?string}>
     */
    private function nettoie(array $lines): array
    {
        return collect($lines)
            ->map(fn (array $ligne) => [
                'medicament' => trim((string) ($ligne['medicament'] ?? '')),
                'posologie' => trim((string) ($ligne['posologie'] ?? '')) ?: null,
                'duree' => trim((string) ($ligne['duree'] ?? '')) ?: null,
            ])
            // Le medicament fait la ligne : sans lui, une posologie seule
            // n'ordonne rien.
            ->filter(fn (array $ligne) => $ligne['medicament'] !== '')
            ->values()
            ->all();
    }
}
