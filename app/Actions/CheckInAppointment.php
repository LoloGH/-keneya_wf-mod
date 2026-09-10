<?php

namespace App\Actions;

use App\Actions\Dme\RecordAppointment;
use App\Jobs\SendSmsJob;
use App\Models\Appointment;
use App\Models\PatientHistory;
use App\Models\Visit;
use App\Services\PatientHistoryRecorder;
use App\Services\TokenAllocator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * « Orienter le patient » : le patient attendu se presente a l'accueil.
 *
 * Ouvre un nouveau passage sous son identite existante et le place directement
 * dans la file du service prevu : sans repasser par un enregistrement complet,
 * puisqu'il est deja connu.
 */
class CheckInAppointment
{
    public function __construct(
        private readonly TokenAllocator $tokens,
        private readonly PatientHistoryRecorder $history,
        private readonly RecordAppointment $dossier,
    ) {}

    public function execute(Appointment $appointment): Visit
    {
        if ($appointment->status !== Appointment::STATUS_SCHEDULED) {
            throw new InvalidArgumentException('Ce rendez-vous n\'est plus en attente du patient.');
        }

        $visit = DB::transaction(function () use ($appointment): Visit {
            $visit = Visit::create([
                'patient_id' => $appointment->patient_id,
                'service_id' => $appointment->service_id,
                'token' => $this->tokens->next($appointment->service_id),
                'status' => Visit::STATUS_WAITING,
                'opened_at' => now(),
            ]);

            $appointment->update([
                'status' => Appointment::STATUS_CHECKED_IN,
                'visit_id' => $visit->getKey(),
            ]);

            $this->dossier->syncStatus($appointment->refresh());

            $visit->load(['service', 'patient']);

            $this->history->record(
                visit: $visit,
                type: PatientHistory::TYPE_REGISTRATION,
                description: sprintf(
                    'Arrivee sur rendez-vous du %s, oriente vers %s (ticket n° %d).',
                    $appointment->scheduled_at->format('d/m/Y H:i'),
                    $visit->service->name,
                    $visit->token,
                ),
                doctor: $appointment->doctor()->first(),
            );

            return $visit;
        });

        SendSmsJob::dispatch($visit->patient->mobile, sprintf(
            '%s : bienvenue. Service %s, ticket n° %d. Dossier %s.',
            config('keneya.name'),
            $visit->service->name,
            $visit->token,
            $visit->patient->patient_code,
        ), $visit->patient);

        return $visit;
    }

    public function markNoShow(Appointment $appointment): Appointment
    {
        if ($appointment->status !== Appointment::STATUS_SCHEDULED) {
            throw new InvalidArgumentException('Seul un rendez-vous en attente peut etre marque « non presente ».');
        }

        $appointment->update(['status' => Appointment::STATUS_NO_SHOW]);

        $this->dossier->syncStatus($appointment);

        return $appointment;
    }

    public function cancel(Appointment $appointment): Appointment
    {
        if ($appointment->status === Appointment::STATUS_CHECKED_IN) {
            throw new InvalidArgumentException('Ce patient est deja arrive : le rendez-vous ne peut plus etre annule.');
        }

        $appointment->update(['status' => Appointment::STATUS_CANCELLED]);

        $this->dossier->syncStatus($appointment);

        return $appointment;
    }
}
