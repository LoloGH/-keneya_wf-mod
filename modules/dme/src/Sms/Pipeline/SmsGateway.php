<?php

declare(strict_types=1);

namespace Keneya\Dme\Sms\Pipeline;

/**
 * Contrat d'une passerelle SMS.
 *
 * Ajouter un opérateur revient à implémenter cette interface et à
 * déclarer sa configuration dans config/sms.php : aucun code métier
 * n'est à modifier. Les contrôleurs n'appellent jamais une passerelle
 * directement : ils passent par SmsService.
 */
interface SmsGateway
{
    /**
     * Transmet un message à la passerelle.
     *
     * L'implémentation ne lève pas d'exception sur une erreur distante :
     * elle retourne un SmsResult en échec, que le service consigne.
     */
    public function send(string $recipient, string $body, ?string $sender = null): SmsResult;

    /**
     * Nom court de la passerelle, consigné avec chaque message.
     */
    public function name(): string;
}
