<?php

namespace App\Actions;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\PatientHistory;
use App\Models\Service;
use App\Services\PatientHistoryRecorder;
use App\Services\SmsGateway;
use Illuminate\Support\Facades\DB;

/**
 * Appel du patient suivant dans la file d'un service.
 */
class CallNextPatient
{
    public function __construct(
        private readonly PatientHistoryRecorder $history,
        private readonly SmsGateway $sms,
    ) {}

    public function execute(Service $service, Doctor $doctor): ?Patient
    {
        $patient = DB::transaction(function () use ($service, $doctor): ?Patient {
            $patient = Patient::query()
                ->where('service_id', $service->getKey())
                ->where('status', Patient::STATUS_WAITING)
                ->orderBy('token')
                ->first();

            if (! $patient) {
                return null;
            }

            $patient->update(['status' => Patient::STATUS_CALLED]);

            $this->history->record(
                patient: $patient,
                type: PatientHistory::TYPE_CONSULTATION,
                description: sprintf(
                    'Appele en consultation au service %s par %s (ticket n° %d).',
                    $service->name,
                    $doctor->name(),
                    $patient->token,
                ),
                serviceId: $service->getKey(),
                doctor: $doctor,
            );

            return $patient;
        });

        if ($patient) {
            $this->sms->send($patient->mobile, sprintf(
                '%s : c\'est votre tour au service %s (ticket n° %d). Merci de vous presenter.',
                config('keneya.name'),
                $service->name,
                $patient->token,
            ));
        }

        return $patient;
    }
}
