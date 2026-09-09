<?php

namespace App\Actions;

use App\Jobs\SendSmsJob;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Visitor;
use App\Services\TokenAllocator;
use App\Support\Audit;
use Illuminate\Support\Facades\Auth;
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
            // Facultatif : une chaine vide vaut absence.
            'id_card_number' => filled($data['idCardNumber'] ?? null) ? $data['idCardNumber'] : null,
            'service_id' => $service->getKey(),
            // Qui l'a recu : c'est la personne que sa note « personnel »
            // concernera, la seule qu'il aura rencontree (v3.2.8, point 4).
            'registered_by_user_id' => Auth::id(),
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

        SendSmsJob::dispatch($visitor->mobile, sprintf(
            '%s : votre fiche visiteur est le %s (service %s, ticket n° %d).%s',
            config('keneya.name'),
            $visitor->visitor_code,
            $visitor->service->name,
            $visitor->token,
            $visitor->patient ? ' Visite a '.$visitor->patient->name.'.' : '',
        ), $visitor);

        return $visitor;
    }
}
