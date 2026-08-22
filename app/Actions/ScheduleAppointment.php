<?php

namespace App\Actions;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Visit;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Prise du prochain rendez-vous, en fin de consultation.
 */
class ScheduleAppointment
{
    public function execute(Visit $visit, Doctor $doctor, Carbon $scheduledAt, ?int $serviceId = null): Appointment
    {
        if ($scheduledAt->isPast()) {
            throw new InvalidArgumentException('La date du rendez-vous doit etre dans le futur.');
        }

        $appointment = DB::transaction(fn (): Appointment => Appointment::create([
            'patient_id' => $visit->patient_id,
            'doctor_id' => $doctor->getKey(),
            'service_id' => $serviceId ?? $doctor->service_id,
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
