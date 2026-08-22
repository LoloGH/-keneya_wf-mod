<?php

namespace App\Actions;

use App\Models\Visitor;
use App\Services\SmsGateway;
use App\Services\TokenAllocator;
use Illuminate\Support\Facades\DB;

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
     * @param  array{name: string, mobile?: ?string, service_id: int, reason?: ?string}  $data
     */
    public function execute(array $data): Visitor
    {
        $visitor = DB::transaction(fn (): Visitor => Visitor::create([
            'name' => $data['name'],
            'mobile' => $data['mobile'] ?? null,
            'service_id' => $data['service_id'],
            'token' => $this->tokens->next($data['service_id']),
            'reason' => $data['reason'] ?? null,
        ]));

        $visitor->load('service');

        if (filled($visitor->mobile)) {
            $this->sms->send($visitor->mobile, sprintf(
                '%s : votre fiche visiteur est le %s (service %s, ticket n° %d).',
                config('keneya.name'),
                $visitor->visitor_code,
                $visitor->service->name,
                $visitor->token,
            ));
        }

        return $visitor;
    }
}
