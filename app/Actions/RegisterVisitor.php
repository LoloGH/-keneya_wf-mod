<?php

namespace App\Actions;

use App\Models\Visitor;
use App\Services\SmsGateway;

/**
 * Enregistrement d'un visiteur (accompagnant, demarche administrative) : pas de
 * dossier medical ni de file d'attente, seulement une fiche tracable.
 */
class RegisterVisitor
{
    public function __construct(private readonly SmsGateway $sms) {}

    /**
     * @param  array{name: string, mobile?: ?string, service_id: int, reason?: ?string}  $data
     */
    public function execute(array $data): Visitor
    {
        $visitor = Visitor::create([
            'name' => $data['name'],
            'mobile' => $data['mobile'] ?? null,
            'service_id' => $data['service_id'],
            'reason' => $data['reason'] ?? null,
        ]);

        $visitor->load('service');

        if (filled($visitor->mobile)) {
            $this->sms->send($visitor->mobile, sprintf(
                '%s : votre fiche visiteur est le %s (service %s).',
                config('keneya.name'),
                $visitor->visitor_code,
                $visitor->service->name,
            ));
        }

        return $visitor;
    }
}
