<?php

namespace App\Actions;

use App\Models\Patient;
use App\Models\Service;
use App\Models\Visitor;
use App\Services\SmsGateway;
use App\Services\TokenAllocator;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Enregistrement d'un visiteur : pas de dossier medical, mais un ticket dans
 * la file du service visite, affiche sur l'ecran de salle d'attente au meme
 * titre que celui d'un patient.
 */
class RegisterVisitor
{
    public function __construct(
        private readonly TokenAllocator $tokens,
        private readonly SmsGateway $sms,
    ) {}

    /**
     * @param  array{name: string, mobile?: ?string, service_id: int, patient_id?: ?int, reason?: ?string}  $data
     */
    public function execute(array $data): Visitor
    {
        $service = Service::findOrFail($data['service_id']);

        // On rend visite a quelqu'un : dans un service clinique, le patient
        // visite est obligatoire. Une demarche administrative, elle, n'en a pas.
        if ($service->isClinique() && blank($data['patient_id'] ?? null)) {
            throw new InvalidArgumentException('Indiquez le patient visite pour un service clinique.');
        }

        $visitor = DB::transaction(fn (): Visitor => Visitor::create([
            'patient_id' => $data['patient_id'] ?? null,
            'name' => $data['name'],
            'mobile' => $data['mobile'] ?? null,
            'service_id' => $service->getKey(),
            'token' => $this->tokens->next($service),
            'reason' => $data['reason'] ?? null,
        ]));

        $visitor->load(['service', 'patient']);

        Audit::log(
            Audit::EVENT_VISITOR_REGISTERED,
            sprintf(
                'Visiteur %s enregistre pour %s%s.',
                $visitor->name,
                $visitor->service->name,
                $visitor->patient ? ' (visite a '.$visitor->patient->patient_code.')' : '',
            ),
            $visitor,
        );

        if (filled($visitor->mobile)) {
            $this->sms->send($visitor->mobile, sprintf(
                '%s : votre fiche visiteur est le %s (service %s, ticket n° %d).%s',
                config('keneya.name'),
                $visitor->visitor_code,
                $visitor->service->name,
                $visitor->token,
                $visitor->patient ? ' Visite a '.$visitor->patient->name.'.' : '',
            ));
        }

        return $visitor;
    }
}
