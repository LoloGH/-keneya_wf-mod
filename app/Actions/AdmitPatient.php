<?php

namespace App\Actions;

use App\Actions\Dme\RecordHospitalization;
use App\Models\Doctor;
use App\Models\Hospitalization;
use App\Models\PatientHistory;
use App\Models\Room;
use App\Models\StaffMember;
use App\Models\Visit;
use App\Services\PatientHistoryRecorder;
use App\Support\Audit;
use App\Support\Caregiver;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Admission d'un patient en hospitalisation (v3.2.1, point 11).
 *
 * L'admission cloture la visite en cours : le patient quitte la file d'attente,
 * il n'attend plus un tour, il occupe un lit. Les deux ecritures sont dans la
 * meme transaction : un patient hospitalise qui resterait dans une file, ou
 * l'inverse, serait une incoherence visible au tableau d'affichage.
 *
 * Une salle pleine **n'empeche pas** l'admission : une urgence hospitaliere
 * depasse parfois la capacite nominale, et un blocage strict serait dangereux
 * plutot que protecteur. L'appelant confirme, on trace, on admet.
 */
class AdmitPatient
{
    public function __construct(
        private readonly PatientHistoryRecorder $history,
        private readonly RecordHospitalization $dossier,
    ) {}

    /**
     * `$doctor` accepte aussi un membre du personnel generique : l'admission
     * est alors signee par `admitted_by_staff_member_id` (v3.3.1). Le controle
     * de service ne change pas : on hospitalise dans son propre service, que
     * l'on soit medecin ou non.
     */
    public function execute(
        Visit $visit,
        Doctor|StaffMember $doctor,
        ?Room $room = null,
        bool $overCapacityConfirmed = false,
    ): Hospitalization {
        if ($visit->isClosed()) {
            throw new InvalidArgumentException('Ce dossier est deja cloture.');
        }

        $agent = Caregiver::of($doctor);

        if ((int) $doctor->service_id !== (int) $visit->service_id) {
            throw new InvalidArgumentException('Seul un praticien du service du patient peut l\'hospitaliser.');
        }

        if ($room && $room->isFull() && ! $overCapacityConfirmed) {
            throw new InvalidArgumentException(sprintf(
                'La salle « %s » est pleine (%s). Confirmez pour admettre au-dela de sa capacite.',
                $room->name,
                $room->occupancyLabel(),
            ));
        }

        $hospitalization = DB::transaction(function () use ($visit, $doctor, $agent, $room): Hospitalization {
            $hospitalization = Hospitalization::create([
                'patient_id' => $visit->patient_id,
                'service_id' => $visit->service_id,
                'visit_id' => $visit->getKey(),
                'room_id' => $room?->getKey(),
                'admitted_by_doctor_id' => $agent->doctorId(),
                'admitted_by_staff_member_id' => $agent->staffMemberId(),
                'admitted_at' => now(),
                'status' => Hospitalization::STATUS_ACTIVE,
            ]);

            // La visite se cloture par le meme mecanisme que partout ailleurs.
            $visit->update([
                'status' => Visit::STATUS_CLOSED,
                'closed_at' => now(),
            ]);

            // Un patient peut occuper un lit huit jours : son dossier medical
            // doit le dire des l'admission, pas au moment de la sortie (v3.3.2).
            $this->dossier->execute($hospitalization, $doctor);

            $visit->load('service');

            $this->history->record(
                visit: $visit,
                type: PatientHistory::TYPE_HOSPITALIZATION_ADMITTED,
                description: sprintf(
                    'Hospitalise en %s par %s.%s',
                    $visit->service->name,
                    $doctor->name(),
                    $room ? ' Salle : '.$room->name.'.' : '',
                ),
                doctor: $doctor,
            );

            return $hospitalization;
        });

        Audit::log(
            Audit::EVENT_PATIENT_ADMITTED,
            sprintf(
                '%s hospitalise en %s par %s.%s',
                $visit->patient->patient_code,
                $visit->service->name,
                $doctor->name(),
                $room ? ' Salle : '.$room->name.'.' : '',
            ),
            $hospitalization,
            ['salle' => $room?->name],
        );

        return $hospitalization;
    }
}
