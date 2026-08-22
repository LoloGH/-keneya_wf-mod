<?php

namespace App\Actions;

use App\Models\Patient;
use App\Models\PatientHistory;
use App\Services\PatientHistoryRecorder;
use App\Services\SmsGateway;
use App\Services\TokenAllocator;
use Illuminate\Support\Facades\DB;

/**
 * Enregistrement d'un patient a l'accueil : cree le dossier, lui attribue un
 * ticket dans la file du service demande, ouvre son historique et l'informe
 * par SMS.
 */
class RegisterPatient
{
    public function __construct(
        private readonly TokenAllocator $tokens,
        private readonly PatientHistoryRecorder $history,
        private readonly SmsGateway $sms,
    ) {}

    /**
     * @param  array{name: string, age: int, gender: string, mobile: string, crno?: ?string, service_id: int}  $data
     */
    public function execute(array $data): Patient
    {
        $patient = DB::transaction(function () use ($data): Patient {
            $patient = Patient::create([
                'name' => $data['name'],
                'age' => $data['age'],
                'gender' => $data['gender'],
                'mobile' => $data['mobile'],
                'crno' => $data['crno'] ?? null,
                'service_id' => $data['service_id'],
                'token' => $this->tokens->next($data['service_id']),
                'status' => Patient::STATUS_WAITING,
            ]);

            $patient->load('service');

            $this->history->record(
                patient: $patient,
                type: PatientHistory::TYPE_REGISTRATION,
                description: sprintf(
                    'Enregistrement a l\'accueil, oriente vers %s (ticket n° %d).',
                    $patient->service->name,
                    $patient->token,
                ),
            );

            return $patient;
        });

        $this->sms->send($patient->mobile, sprintf(
            '%s : bonjour %s. Votre dossier est le %s. Vous etes attendu(e) au service %s, ticket n° %d.',
            config('keneya.name'),
            $patient->name,
            $patient->patient_code,
            $patient->service->name,
            $patient->token,
        ));

        return $patient;
    }
}
