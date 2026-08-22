<?php

namespace App\Actions;

use App\Models\Doctor;
use App\Models\PatientHistory;
use App\Models\Referral;
use App\Services\PatientHistoryRecorder;
use App\Services\SmsGateway;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Saisie du resultat par le service destinataire, puis retour au prescripteur.
 *
 * Le prescripteur est prevenu par SMS si son numero est renseigne ; dans tous
 * les cas le resultat apparait dans son panneau « Resultats recus » au
 * prochain rafraichissement (wire:poll), sans WebSocket.
 */
class CompleteReferral
{
    public function __construct(
        private readonly PatientHistoryRecorder $history,
        private readonly SmsGateway $sms,
    ) {}

    public function execute(Referral $referral, Doctor $completedBy, string $resultText): Referral
    {
        if ($referral->status === Referral::STATUS_DONE) {
            throw new InvalidArgumentException('Ce renvoi a deja recu un resultat.');
        }

        if ((int) $completedBy->service_id !== (int) $referral->to_service_id) {
            throw new InvalidArgumentException('Seul un praticien du service destinataire peut saisir ce resultat.');
        }

        $referral = DB::transaction(function () use ($referral, $completedBy, $resultText): Referral {
            $referral->update([
                'status' => Referral::STATUS_DONE,
                'result_text' => $resultText,
                'completed_by_doctor_id' => $completedBy->getKey(),
                'completed_at' => now(),
            ]);

            $referral->load(['patient', 'toService']);

            $this->history->record(
                patient: $referral->patient,
                type: PatientHistory::TYPE_REFERRAL_RESULT,
                description: sprintf(
                    'Resultat de %s saisi par %s : %s',
                    $referral->toService->name,
                    $completedBy->name(),
                    $resultText,
                ),
                serviceId: $referral->to_service_id,
                doctor: $completedBy,
                referral: $referral,
            );

            return $referral;
        });

        $prescriber = $referral->fromDoctor()->first();

        if ($prescriber && filled($prescriber->phone)) {
            $this->sms->send($prescriber->phone, sprintf(
                '%s : resultat disponible pour le patient %s (%s), renvoye vers %s.',
                config('keneya.name'),
                $referral->patient->name,
                $referral->patient->patient_code,
                $referral->toService->name,
            ));
        }

        return $referral;
    }
}
