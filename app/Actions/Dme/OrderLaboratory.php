<?php

namespace App\Actions\Dme;

use App\Actions\Dme\Concerns\ResolvesMedicalRecord;
use App\Models\Doctor;
use App\Models\StaffMember;
use App\Models\Visit;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Keneya\Dme\Models\LabOrder;

/**
 * Demande d'examen biologique, posee depuis /service (v3.3.1).
 *
 * Une demande porte plusieurs analyses, numeration, glycemie, goutte epaisse
 *, et le module les tient en lignes distinctes plutot qu'en texte libre :
 * c'est ce qui permettra plus tard d'y accrocher des resultats chiffres.
 *
 * Ce que WorkFlow fait deja et que ceci ne remplace pas : le renvoi vers le
 * laboratoire, qui fait circuler le patient et sa facturation. Les deux
 * coexistent : le renvoi conduit le patient, la demande documente l'acte.
 * C'est la separation posee au §3 de la note du v3.3.1.
 */
class OrderLaboratory
{
    use ResolvesMedicalRecord;

    /** @var array<string, string> */
    /**
     * Garde-fou : une demande ne porte pas quarante analyses.
     *
     * Ici plutot que dans le formulaire : la borne appartient a l'acte, non a
     * l'ecran qui le saisit, et un gabarit Blade ne peut pas lire une
     * constante de trait.
     */
    public const MAX_ANALYSES = 15;

    public const PRIORITIES = [
        'routine' => 'Courante',
        'urgent' => 'Urgente',
        'vital' => 'Vitale',
    ];

    /**
     * @param  array{
     *     requested_at: string,
     *     priority: string,
     *     indication?: ?string,
     *     exams: array<int, array{name: string, category?: ?string}>,
     * }  $data
     */
    public function execute(Visit $visit, Doctor|StaffMember $doctor, array $data): LabOrder
    {
        $dossier = $this->dossierMedical($visit);

        $demande = DB::transaction(function () use ($dossier, $doctor, $data): LabOrder {
            $demande = $dossier->labOrders()->create([
                'doctor_id' => $doctor->user_id,
                'requested_at' => $data['requested_at'],
                'priority' => $data['priority'],
                'indication' => $data['indication'] ?: null,
                'status' => 'requested',
            ]);

            foreach ($data['exams'] as $analyse) {
                if (blank($analyse['name'] ?? null)) {
                    continue;
                }

                $demande->items()->create([
                    'exam_name' => $analyse['name'],
                    'category' => $analyse['category'] ?? null ?: null,
                    'status' => 'requested',
                ]);
            }

            return $demande;
        });

        Audit::log(
            Audit::EVENT_LAB_ORDERED,
            sprintf(
                'Demande d\'analyses %s posee pour %s par %s (%d analyse(s)).',
                $demande->order_number,
                $visit->patient->patient_code,
                $doctor->name(),
                $demande->items()->count(),
            ),
            $visit,
        );

        return $demande;
    }
}
