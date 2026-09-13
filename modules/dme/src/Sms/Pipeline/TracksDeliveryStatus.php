<?php

declare(strict_types=1);

namespace Keneya\Dme\Sms\Pipeline;

/**
 * Capacité optionnelle : une passerelle qui sait dire ce qu'est devenu
 * un message déjà transmis.
 *
 * Séparée de SmsGateway pour ne pas contraindre les passerelles qui ne
 * savent pas suivre l'acheminement (le pilote de journalisation, par
 * exemple) à implémenter une méthode qu'elles ne peuvent pas honorer.
 */
interface TracksDeliveryStatus
{
    /**
     * Interroge la passerelle sur l'état d'un message déjà transmis.
     *
     * @param  string  $gatewayMessageId  identifiant retourné par la passerelle
     */
    public function status(string $gatewayMessageId): SmsResult;
}
