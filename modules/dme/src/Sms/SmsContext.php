<?php

declare(strict_types=1);

namespace Keneya\Dme\Sms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Contexte d'un SMS, encodé sous forme de chaîne.
 *
 * Le contrat d'envoi ({@see \Keneya\Dme\Contracts\SmsDispatcherContract})
 * ne transporte qu'un destinataire, un texte et une chaîne de contexte
 * optionnelle. Cette classe fixe le format de cette chaîne, afin que le
 * module puisse continuer à relier un message à son patient, à l'acte qui
 * l'a déclenché et à l'heure prévue d'envoi, sans que le code métier ait
 * à connaître l'implémentation qui envoie.
 *
 * Format : des couples `clé=valeur` séparés par des points-virgules, les
 * valeurs étant encodées pour l'URL.
 *
 *   patient=12;subject=Keneya%5CDme%5CModels%5CAppointment;subject_id=34;
 *   template=appointment_reminder;send_at=2026-09-08T09%3A30%3A00%2B00%3A00
 *
 * Une implémentation hôte qui ne sait rien de ce format n'a pas à s'en
 * soucier : la chaîne reste lisible et peut être journalisée telle quelle.
 */
final class SmsContext
{
    public function __construct(
        public readonly ?int $patientId = null,
        public readonly ?string $subjectType = null,
        public readonly ?int $subjectId = null,
        public readonly ?string $templateKey = null,
        public readonly ?Carbon $sendAt = null,
    ) {
    }

    /**
     * Construit un contexte à partir des objets du domaine.
     */
    public static function for(
        ?Model $patient = null,
        ?object $subject = null,
        ?string $templateKey = null,
        ?Carbon $sendAt = null,
    ): self {
        $subjectId = null;

        if ($subject instanceof Model) {
            $key = $subject->getKey();
            $subjectId = is_numeric($key) ? (int) $key : null;
        }

        return new self(
            patientId: $patient?->getKey() === null ? null : (int) $patient->getKey(),
            subjectType: $subject !== null ? $subject::class : null,
            subjectId: $subjectId,
            templateKey: $templateKey,
            sendAt: $sendAt,
        );
    }

    /**
     * Relit une chaîne de contexte produite par ce module.
     *
     * Toute chaîne inconnue est acceptée sans erreur : elle produit
     * simplement un contexte vide, ce qui laisse le message partir.
     */
    public static function parse(?string $context): self
    {
        if ($context === null || trim($context) === '') {
            return new self;
        }

        $values = [];

        foreach (explode(';', $context) as $pair) {
            if (! str_contains($pair, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $pair, 2);
            $values[trim($key)] = urldecode($value);
        }

        $sendAt = null;

        if (isset($values['send_at'])) {
            try {
                $sendAt = Carbon::parse($values['send_at']);
            } catch (\Throwable) {
                $sendAt = null;
            }
        }

        return new self(
            patientId: isset($values['patient']) ? (int) $values['patient'] : null,
            subjectType: $values['subject'] ?? null,
            subjectId: isset($values['subject_id']) ? (int) $values['subject_id'] : null,
            templateKey: $values['template'] ?? null,
            sendAt: $sendAt,
        );
    }

    public function __toString(): string
    {
        $pairs = array_filter([
            'patient' => $this->patientId,
            'subject' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'template' => $this->templateKey,
            'send_at' => $this->sendAt?->toIso8601String(),
        ], static fn ($value) => $value !== null && $value !== '');

        $encoded = [];

        foreach ($pairs as $key => $value) {
            $encoded[] = $key.'='.urlencode((string) $value);
        }

        return implode(';', $encoded);
    }

    /**
     * Chaîne à passer au contrat, ou null si le contexte est vide.
     */
    public function toContextString(): ?string
    {
        $encoded = (string) $this;

        return $encoded === '' ? null : $encoded;
    }
}
