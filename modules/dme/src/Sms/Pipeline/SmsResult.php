<?php

declare(strict_types=1);

namespace Keneya\Dme\Sms\Pipeline;

/**
 * Résultat d'une interaction avec une passerelle SMS.
 *
 * Objet immuable appartenant au service SMS : il ne dépend d'aucun modèle
 * du domaine médical, ce qui permet d'extraire le service tel quel lors
 * de la phase 2.
 *
 * Le champ `state` distingue explicitement « accepté par la passerelle »
 * de « réellement envoyé ». C'est une exigence de sécurité de l'exploitant :
 * l'application ne doit jamais annoncer un SMS comme envoyé tant que le
 * fournisseur ne l'a pas confirmé. SMSGate, par exemple, répond d'abord
 * « Pending » : le message n'a alors même pas atteint le téléphone.
 */
final readonly class SmsResult
{
    /** Le fournisseur a accepté le message ; il n'est pas encore parti. */
    public const STATE_ACCEPTED = 'accepted';

    /** Le fournisseur confirme l'émission vers l'opérateur. */
    public const STATE_SENT = 'sent';

    /** Accusé de réception de l'opérateur ou du terminal. */
    public const STATE_DELIVERED = 'delivered';

    /** Échec définitif signalé par le fournisseur. */
    public const STATE_FAILED = 'failed';

    /** Le message a été annulé côté fournisseur. */
    public const STATE_CANCELLED = 'cancelled';

    /**
     * @param  array<string, mixed>  $response
     */
    public function __construct(
        public bool $successful,
        public string $gateway,
        public string $state,
        public ?string $messageId = null,
        public ?string $error = null,
        public array $response = [],
    ) {
    }

    /**
     * Le fournisseur a pris en charge le message sans l'avoir encore émis.
     *
     * @param  array<string, mixed>  $response
     */
    public static function accepted(string $gateway, ?string $messageId = null, array $response = []): self
    {
        return new self(true, $gateway, self::STATE_ACCEPTED, $messageId, null, $response);
    }

    /**
     * Le fournisseur confirme l'émission.
     *
     * @param  array<string, mixed>  $response
     */
    public static function sent(string $gateway, ?string $messageId = null, array $response = []): self
    {
        return new self(true, $gateway, self::STATE_SENT, $messageId, null, $response);
    }

    /**
     * Le message a été remis au destinataire.
     *
     * @param  array<string, mixed>  $response
     */
    public static function delivered(string $gateway, ?string $messageId = null, array $response = []): self
    {
        return new self(true, $gateway, self::STATE_DELIVERED, $messageId, null, $response);
    }

    /**
     * Alias historique de `sent()`, conservé pour les passerelles de
     * simulation dont l'envoi est immédiat et sans acheminement réel.
     *
     * @param  array<string, mixed>  $response
     */
    public static function success(string $gateway, ?string $messageId = null, array $response = []): self
    {
        return self::sent($gateway, $messageId, $response);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public static function failure(string $gateway, string $error, array $response = []): self
    {
        return new self(false, $gateway, self::STATE_FAILED, null, $error, $response);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public static function cancelled(string $gateway, ?string $messageId = null, array $response = []): self
    {
        return new self(false, $gateway, self::STATE_CANCELLED, $messageId, null, $response);
    }

    /**
     * Un état final ne sera plus interrogé par le suivi d'acheminement.
     */
    public function isFinal(): bool
    {
        return in_array($this->state, [self::STATE_DELIVERED, self::STATE_FAILED, self::STATE_CANCELLED], true);
    }
}
