<?php

namespace App\Services;

/**
 * Issue d'une tentative d'envoi (v3.2.8).
 *
 * `send()` ne renvoyait qu'un booleen : suffisant tant que l'appel etait
 * synchrone et son resultat ignore, insuffisant des lors qu'une file d'attente
 * doit decider s'il vaut la peine de reessayer, et qu'une table doit conserver
 * la raison de l'echec.
 */
final class SmsSendResult
{
    private function __construct(
        public readonly bool $successful,
        public readonly bool $retryable,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $failureReason = null,
    ) {}

    /** La passerelle a accepte le message. */
    public static function sent(?string $providerMessageId = null): self
    {
        return new self(successful: true, retryable: false, providerMessageId: $providerMessageId);
    }

    /**
     * Echec passager : passerelle injoignable, telephone endormi, erreur 5xx.
     * Une nouvelle tentative a des chances d'aboutir.
     */
    public static function failed(string $reason): self
    {
        return new self(successful: false, retryable: true, failureReason: $reason);
    }

    /**
     * Echec definitif : numero vide, passerelle desactivee, identifiants
     * refuses. Reessayer trois fois ne ferait que retarder le meme constat.
     */
    public static function rejected(string $reason): self
    {
        return new self(successful: false, retryable: false, failureReason: $reason);
    }
}
