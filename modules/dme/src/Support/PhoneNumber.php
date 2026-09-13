<?php

declare(strict_types=1);

namespace Keneya\Dme\Support;

/**
 * Normalisation des numéros de téléphone vers un format E.164 simplifié.
 *
 * Une passerelle refuse généralement « 70 00 10 01 » mais accepte
 * « +22370001001 ». La normalisation est centralisée ici afin que les
 * numéros stockés dans l'historique SMS soient comparables entre eux.
 */
final class PhoneNumber
{
    public static function normalize(?string $number, ?string $countryCode = null): ?string
    {
        $number = trim((string) $number);

        if ($number === '') {
            return null;
        }

        $countryCode ??= (string) config('dme.sms.default_country_code', '+223');

        // Préfixe international « 00 » -> « + »
        if (str_starts_with($number, '00')) {
            $number = '+'.mb_substr($number, 2);
        }

        $hasPlus = str_starts_with($number, '+');
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        if ($digits === '') {
            return null;
        }

        if ($hasPlus) {
            return '+'.$digits;
        }

        // Numéro national : on applique l'indicatif configuré.
        return rtrim($countryCode, '+') === ''
            ? '+'.$digits
            : $countryCode.$digits;
    }

    /**
     * Un numéro est jugé envoyable s'il comporte assez de chiffres pour
     * être routé. Contrôle volontairement souple : la passerelle reste
     * l'autorité finale.
     */
    public static function isSendable(?string $number): bool
    {
        $normalized = self::normalize($number);

        return $normalized !== null
            && mb_strlen(preg_replace('/\D+/', '', $normalized) ?? '') >= 8;
    }
}
