<?php

namespace App\Actions;

use App\Models\Doctor;
use App\Models\PatientHistory;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Services\PatientHistoryRecorder;
use App\Services\SmsGateway;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Appel du patient suivant dans la file d'un service.
 *
 * L'appelant peut etre un medecin ou, aux caisses, un caissier : la file
 * fonctionne de la meme façon partout. Seul un medecin est consigne comme tel
 * dans l'historique, un caissier n'ayant pas de role clinique.
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

    public function execute(Service $service, Doctor|User $calledBy): ?Visit
    {
        $doctor = $calledBy instanceof Doctor ? $calledBy : null;
        $nom = $calledBy instanceof Doctor ? $calledBy->name() : $calledBy->name;

        $visit = DB::transaction(function () use ($service, $doctor, $nom): ?Visit {
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
                    'Appele au service %s par %s (ticket n° %d).',
                    $service->name,
                    $nom,
                    $visit->token,
                ),
                serviceId: $service->getKey(),
                doctor: $doctor,
            );

            return $visit;
        });

        if (! $visit) {
            return null;
        }

        // Acte metier, pas une simple ecriture : on le journalise en clair
        // plutot que de laisser un diff d'attributs illisible.
        Audit::log(
            Audit::EVENT_PATIENT_CALLED,
            sprintf(
                '%s appele au service %s par %s (ticket n° %d).',
                $visit->patient->patient_code,
                $service->name,
                $nom,
                $visit->token,
            ),
            $visit,
        );

        $this->sms->send($visit->patient->mobile, sprintf(
            '%s : c\'est votre tour au service %s (ticket n° %d). Merci de vous presenter.',
            config('keneya.name'),
            $service->name,
            $visit->token,
        ));

        return $visit;
    }
}
