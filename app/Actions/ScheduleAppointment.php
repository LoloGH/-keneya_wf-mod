<?php

namespace App\Actions;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\StaffMember;
use App\Models\Visit;
use App\Support\Audit;
use App\Support\Caregiver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Prise du prochain rendez-vous, en fin de consultation.
 */
class ScheduleAppointment
{
    /**
     * `$doctor` accepte aussi un membre du personnel generique : le rendez-vous
     * est alors signe par `staff_member_id`, jamais par `doctor_id`, on ne
     * fabrique pas de faux medecins (v3.3.1).
     */
    public function execute(Visit $visit, Doctor|StaffMember $doctor, Carbon $scheduledAt, ?int $serviceId = null): Appointment
    {
        if ($scheduledAt->isPast()) {
            throw new InvalidArgumentException('La date du rendez-vous doit etre dans le futur.');
        }

        $agent = Caregiver::of($doctor);

        $appointment = DB::transaction(fn (): Appointment => Appointment::create([
            'patient_id' => $visit->patient_id,
            'doctor_id' => $agent->doctorId(),
            'staff_member_id' => $agent->staffMemberId(),
            'service_id' => $serviceId ?? $agent->serviceId(),
            'scheduled_at' => $scheduledAt,
            'status' => Appointment::STATUS_SCHEDULED,
        ]));

        // Journalise seulement une fois la transaction validee.
        Audit::log(
            Audit::EVENT_APPOINTMENT_CREATED,
            sprintf('Rendez-vous fixe au %s.', $scheduledAt->format('d/m/Y H:i')),
            $appointment,
        );

        return $appointment;
    }
}
