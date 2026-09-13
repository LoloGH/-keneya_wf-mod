<?php

declare(strict_types=1);

namespace Keneya\Dme\Sms\Pipeline;

use Keneya\Dme\Contracts\SimulatesSmsDelivery;
use Keneya\Dme\Contracts\SmsDispatcherContract;
use Keneya\Dme\Models\SmsTemplate;
use Keneya\Dme\Sms\SmsContext;

/**
 * Implémentation de secours branchée sur la file d'attente interne du
 * module : persistance du message, mise en file, passerelle, historique.
 *
 * C'est le comportement historique de l'application autonome, conservé
 * tant que le module doit pouvoir envoyer des SMS sans hôte. Lorsque
 * Keneya Workflow fournira sa propre implémentation du contrat, tout ce
 * répertoire (`src/Sms/Pipeline`) pourra être retiré d'un bloc sans
 * toucher au code métier, qui ne connaît que le contrat.
 */
final class QueuedSmsDispatcher implements SimulatesSmsDelivery, SmsDispatcherContract
{
    public function __construct(
        private readonly SmsService $sms,
        private readonly SmsGatewayManager $gateways,
    ) {
    }

    /**
     * Les passerelles « log » et « array » n'émettent rien : l'interface
     * doit le dire, pour qu'un utilisateur ne croie jamais avoir prévenu
     * un patient alors qu'aucun message n'est parti.
     */
    public function isSimulated(): bool
    {
        return $this->gateways->isSimulated();
    }

    public function dispatch(string $to, string $message, ?string $context = null): void
    {
        $parsed = SmsContext::parse($context);

        $this->sms->send(
            recipient: $to,
            body: $message,
            context: [
                'patient_id' => $parsed->patientId,
                'context_type' => $parsed->subjectType,
                'context_id' => $parsed->subjectId,
            ],
            scheduledFor: $parsed->sendAt,
            template: $this->template($parsed->templateKey),
        );
    }

    /**
     * Retrouve le modèle de texte à l'origine du message, pour que
     * l'historique sache de quel modèle il provient. Son absence n'est
     * pas une erreur : le texte, lui, est déjà rendu.
     */
    private function template(?string $key): ?SmsTemplate
    {
        if ($key === null) {
            return null;
        }

        return SmsTemplate::query()->where('key', $key)->first();
    }
}
