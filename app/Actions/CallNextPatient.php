<?php

namespace App\Actions;

use App\Models\Doctor;
use App\Models\PatientHistory;
use App\Models\Service;
use App\Models\Visit;
use App\Services\PatientHistoryRecorder;
use App\Services\SmsGateway;
use Illuminate\Support\Facades\DB;

/**
 * Appel du patient suivant dans la file d'un service.
 *
 * Une visite cloturee est sortie de la file : « Appeler le suivant » ne la
 * proposera plus, meme si son dossier reste consultable.
 */
class CallNextPatient
{
    public function __construct(
        private readonly PatientHistoryRecorder $history,
        private readonly SmsGateway $sms,
    ) {}

    public function execute(Service $service, Doctor $doctor): ?Visit
    {
        $visit = DB::transaction(function () use ($service, $doctor): ?Visit {
            $visit = Visit::query()
                ->with('patient')
                ->inTodaysQueue($service->getKey())
                ->where('status', Visit::STATUS_WAITING)
                ->orderBy('token')
                ->first();

            if (! $visit) {
                return null;
            }

            $visit->update(['status' => Visit::STATUS_CALLED]);

            $this->history->record(
                visit: $visit,
                type: PatientHistory::TYPE_CONSULTATION,
                description: sprintf(
                    'Appele en consultation au service %s par %s (ticket n° %d).',
                    $service->name,
                    $doctor->name(),
                    $visit->token,
                ),
                serviceId: $service->getKey(),
                doctor: $doctor,
            );

            return $visit;
        });

        if ($visit) {
            $this->sms->send($visit->patient->mobile, sprintf(
                '%s : c\'est votre tour au service %s (ticket n° %d). Merci de vous presenter.',
                config('keneya.name'),
                $service->name,
                $visit->token,
            ));
        }

        return $visit;
    }
}
