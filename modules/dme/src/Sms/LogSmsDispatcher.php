<?php

declare(strict_types=1);

namespace Keneya\Dme\Sms;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Log\LogManager;
use Keneya\Dme\Contracts\SimulatesSmsDelivery;
use Keneya\Dme\Contracts\SmsDispatcherContract;
use Keneya\Dme\Support\PhoneNumber;
use RuntimeException;

/**
 * Implémentation de secours : le message est journalisé, jamais émis.
 *
 * Elle existe pour que le module reste testable et utilisable seul, sans
 * passerelle ni file d'attente : notamment dans la suite de tests et dans
 * une application hôte de démonstration.
 *
 * Le numéro est tout de même normalisé, afin qu'un numéro inexploitable
 * soit détecté ici comme il le serait avec une vraie passerelle.
 */
final class LogSmsDispatcher implements SimulatesSmsDelivery, SmsDispatcherContract
{
    public function __construct(
        private readonly LogManager $log,
        private readonly Config $config,
    ) {
    }

    public function dispatch(string $to, string $message, ?string $context = null): void
    {
        $normalized = PhoneNumber::normalize($to);

        if ($normalized === null) {
            throw new RuntimeException('Numéro de destinataire invalide : '.$to);
        }

        $channel = $this->config->get('dme.sms.gateways.log.channel');

        $this->log->channel($channel)->info('SMS simulé (aucun envoi réel)', [
            'destinataire' => $normalized,
            'message' => $message,
            'contexte' => $context,
        ]);
    }

    public function isSimulated(): bool
    {
        return true;
    }
}
