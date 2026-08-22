<?php

namespace App\Actions;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\PatientHistory;
use App\Models\Referral;
use App\Models\Service;
use App\Services\PatientHistoryRecorder;
use App\Services\SmsGateway;
use App\Services\TokenAllocator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Renvoi d'un patient vers un autre service.
 *
 * Le patient_id ne change jamais : c'est le meme dossier qui traverse les
 * services, il n'est jamais duplique. Seul son service courant et son ticket
 * changent.
 */
class SendReferral
{
    public function __construct(
        private readonly TokenAllocator $tokens,
        private readonly PatientHistoryRecorder $history,
        private readonly SmsGateway $sms,
    ) {}

    public function execute(Patient $patient, Doctor $fromDoctor, Service $toService, string $instructions): Referral
    {
        $fromService = $patient->service()->firstOrFail();

        if ($toService->is($fromService)) {
            throw new InvalidArgumentException('Le service destinataire doit etre different du service actuel du patient.');
        }

        $referral = DB::transaction(function () use ($patient, $fromDoctor, $fromService, $toService, $instructions): Referral {
            $referral = Referral::create([
                'patient_id' => $patient->getKey(),
                'from_service_id' => $fromService->getKey(),
                'to_service_id' => $toService->getKey(),
                'from_doctor_id' => $fromDoctor->getKey(),
                'instructions' => $instructions,
                'status' => Referral::STATUS_PENDING,
            ]);

            $patient->update([
                'service_id' => $toService->getKey(),
                'token' => $this->tokens->next($toService),
                'status' => Patient::STATUS_WAITING,
            ]);

            $this->history->record(
                patient: $patient,
                type: PatientHistory::TYPE_REFERRAL_SENT,
                description: sprintf(
                    'Renvoye de %s vers %s par %s. Instructions : %s',
                    $fromService->name,
                    $toService->name,
                    $fromDoctor->name(),
                    $instructions,
                ),
                serviceId: $toService->getKey(),
                doctor: $fromDoctor,
                referral: $referral,
            );

            return $referral;
        });

        $this->sms->send($patient->mobile, sprintf(
            '%s : vous etes oriente(e) vers le service %s, ticket n° %d. Dossier %s.',
            config('keneya.name'),
            $toService->name,
            $patient->fresh()->token,
            $patient->patient_code,
        ));

        return $referral;
    }
}
