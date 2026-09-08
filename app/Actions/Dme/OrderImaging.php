<?php

namespace App\Actions\Dme;

use App\Actions\Dme\Concerns\ResolvesMedicalRecord;
use App\Models\Doctor;
use App\Models\Visit;
use App\Support\Audit;
use Keneya\Dme\Models\ImagingOrder;

/**
 * Demande d'imagerie, posee depuis /service (v3.3.1).
 *
 * La modalite est exigee — echographie, radiographie, scanner — parce qu'elle
 * conditionne tout le reste : le service qui realise l'examen, sa duree, sa
 * facturation, et le compte rendu attendu. Une demande « imagerie » sans autre
 * precision n'aide personne au bout de la chaine.
 *
 * La region examinee est facultative sans etre accessoire : « echographie »
 * seul ne dit pas s'il s'agit d'une abdominale ou d'une obstetricale.
 */
class OrderImaging
{
    use ResolvesMedicalRecord;

    /**
     * @param  array{
     *     modality: string,
     *     body_site?: ?string,
     *     requested_at: string,
     *     priority: string,
     *     indication?: ?string,
     * }  $data
     */
    public function execute(Visit $visit, Doctor $doctor, array $data): ImagingOrder
    {
        $dossier = $this->dossierMedical($visit);

        $demande = $dossier->imagingOrders()->create([
            'doctor_id' => $doctor->user_id,
            'modality' => $data['modality'],
            'body_site' => $data['body_site'] ?: null,
            'requested_at' => $data['requested_at'],
            'priority' => $data['priority'],
            'indication' => $data['indication'] ?: null,
            'status' => 'requested',
        ]);

        Audit::log(
            Audit::EVENT_IMAGING_ORDERED,
            sprintf(
                'Demande d\'imagerie %s (%s) posee pour %s par %s.',
                $demande->order_number,
                mb_strtolower(ImagingOrder::MODALITIES[$data['modality']] ?? $data['modality']),
                $visit->patient->patient_code,
                $doctor->name(),
            ),
            $visit,
        );

        return $demande;
    }
}
