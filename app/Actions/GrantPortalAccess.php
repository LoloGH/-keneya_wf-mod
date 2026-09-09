<?php

namespace App\Actions;

use App\Models\Patient;
use App\Models\PortalAccessAttempt;
use App\Support\Audit;

/**
 * Validation du code a quatre chiffres du portail patient (v3.2, point 7).
 *
 * Le lien ne perime jamais, comme demande. La securite tient donc a deux
 * couches et non a une : le `portal_token` est un UUID non devinable, il faut
 * deja connaitre ce lien precis pour tenter quoi que ce soit, et ce
 * verrouillage temporaire empeche d'essayer les 10 000 codes possibles.
 *
 * Le verrouillage est volontairement temporaire : un patient qui se trompe
 * deux fois ne doit pas se retrouver bloque a vie devant ses propres documents.
 */
class GrantPortalAccess
{
    /**
     * @return array{granted: bool, message: ?string}
     */
    public function attempt(Patient $patient, string $code): array
    {
        $tentative = PortalAccessAttempt::firstOrCreate(
            ['patient_id' => $patient->getKey()],
            ['failures' => 0],
        );

        if ($tentative->isLocked()) {
            return [
                'granted' => false,
                'message' => sprintf(
                    'Trop de tentatives. Reessayez dans %d minute(s).',
                    $tentative->minutesRemaining(),
                ),
            ];
        }

        // Comparaison a temps constant : le code est court, autant ne pas
        // laisser fuiter d'information par la duree de la reponse.
        if (hash_equals((string) $patient->access_code, trim($code))) {
            $tentative->update(['failures' => 0, 'locked_until' => null]);

            Audit::log(
                Audit::EVENT_PORTAL_ACCESS,
                sprintf('Consultation du portail par le patient %s.', $patient->patient_code),
            );

            return ['granted' => true, 'message' => null];
        }

        $echecs = $tentative->failures + 1;

        if ($echecs >= PortalAccessAttempt::MAX_FAILURES) {
            $tentative->update([
                'failures' => 0,
                'locked_until' => now()->addMinutes(PortalAccessAttempt::LOCK_MINUTES),
            ]);

            return [
                'granted' => false,
                'message' => sprintf(
                    'Trop de tentatives. Reessayez dans %d minutes.',
                    PortalAccessAttempt::LOCK_MINUTES,
                ),
            ];
        }

        $tentative->update(['failures' => $echecs]);

        return [
            'granted' => false,
            'message' => sprintf(
                'Code incorrect. Il vous reste %d tentative(s).',
                PortalAccessAttempt::MAX_FAILURES - $echecs,
            ),
        ];
    }
}
