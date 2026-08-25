<?php

namespace App\Actions;

use App\Models\Patient;
use App\Models\PatientHistory;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Services\PatientHistoryRecorder;
use App\Services\SmsGateway;
use App\Services\TokenAllocator;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * « Confirmer et orienter » depuis la caisse (v3.2, point 6).
 *
 * Le caissier encaisse, puis relache le patient vers le service qui l'attend :
 * la visite quitte la file de la caisse, bascule sur sa destination reelle et
 * y reçoit un nouveau ticket.
 */
class ConfirmCaissePayment
{
    public function __construct(
        private readonly TokenAllocator $tokens,
        private readonly PatientHistoryRecorder $history,
        private readonly SmsGateway $sms,
    ) {}

    public function execute(Visit $visit, User $cashier, int $amount): Visit
    {
        if (! $visit->awaitsPayment()) {
            throw new InvalidArgumentException("Cette visite n'attend aucun paiement.");
        }

        if ($visit->isClosed()) {
            throw new InvalidArgumentException('Ce dossier est cloture.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('Le montant doit etre superieur a zero.');
        }

        $caisse = $visit->service()->firstOrFail();
        $destination = $visit->pendingNextService()->firstOrFail();

        $visit = DB::transaction(function () use ($visit, $cashier, $amount, $caisse, $destination): Visit {
            // Le type d'encaissement suit la caisse : ticket de consultation
            // d'un cote, acte ou examen de l'autre.
            Payment::create([
                'patient_id' => $visit->patient_id,
                'visit_id' => $visit->getKey(),
                'type' => $caisse->name === Service::CAISSE_SERVICES
                    ? Payment::TYPE_SERVICE
                    : Payment::TYPE_TICKET,
                'service_id' => $destination->getKey(),
                'amount' => $amount,
                'status' => Payment::STATUS_PAID,
                'recorded_by_user_id' => $cashier->getKey(),
            ]);

            $visit->update([
                'service_id' => $destination->getKey(),
                'pending_next_service_id' => null,
                'token' => $this->tokens->next($destination),
                'status' => Visit::STATUS_WAITING,
            ]);

            $visit->refresh()->load(['service', 'patient']);

            $this->history->record(
                visit: $visit,
                type: PatientHistory::TYPE_PAYMENT_CONFIRMED,
                description: sprintf(
                    'Paiement de %s FCFA encaisse a %s par %s. Oriente vers %s (ticket n° %d).',
                    number_format($amount, 0, ',', ' '),
                    $caisse->name,
                    $cashier->name,
                    $destination->name,
                    $visit->token,
                ),
                serviceId: $destination->getKey(),
            );

            return $visit;
        });

        Audit::log(
            Audit::EVENT_PAYMENT_CONFIRMED,
            sprintf(
                'Paiement de %s FCFA confirme a %s ; %s oriente vers %s.',
                number_format($amount, 0, ',', ' '),
                $caisse->name,
                $visit->patient->patient_code,
                $destination->name,
            ),
            $visit,
            ['montant' => $amount, 'destination' => $destination->name],
        );

        $this->sms->send($visit->patient->mobile, sprintf(
            '%s : paiement enregistre. Vous etes attendu(e) au service %s, ticket n° %d.',
            config('keneya.name'),
            $destination->name,
            $visit->token,
        ));

        return $visit;
    }

    /**
     * Retrouve un patient a la caisse par son code ou son nom, pour eviter au
     * caissier de chercher dans toute la file.
     *
     * @return Collection<int, Patient>
     */
    public function search(string $terme)
    {
        return Patient::query()
            ->where('patient_code', 'like', "%{$terme}%")
            ->orWhere('name', 'like', "%{$terme}%")
            ->limit(10)
            ->get();
    }
}
