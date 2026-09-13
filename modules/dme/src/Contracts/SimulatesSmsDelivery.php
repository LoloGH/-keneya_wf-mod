<?php

declare(strict_types=1);

namespace Keneya\Dme\Contracts;

/**
 * Complément facultatif à {@see SmsDispatcherContract}.
 *
 * Une implémentation qui n'émet pas réellement de SMS, journalisation,
 * passerelle de développement, bac à sable, le déclare ici, afin que
 * l'interface puisse en avertir l'utilisateur plutôt que de le laisser
 * croire qu'un message est parti.
 *
 * Une implémentation d'hôte qui envoie pour de bon n'a pas à implémenter
 * cette interface : le module considère alors que l'envoi est réel.
 */
interface SimulatesSmsDelivery
{
    /**
     * L'envoi est-il simulé, c'est-à-dire sans émission réelle ?
     */
    public function isSimulated(): bool;
}
