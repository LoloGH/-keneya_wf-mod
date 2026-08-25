<?php

namespace App\Actions;

use App\Models\Service;

/**
 * Routage sous condition de paiement (v3.2, point 6).
 *
 * Dans cet hopital, on regle avant d'etre pris en charge. Toute visite qui vise
 * un service facture passe donc d'abord par une caisse : la destination reelle
 * est mise de cote dans `pending_next_service_id`, et le service courant devient
 * la caisse.
 *
 * Ce n'est qu'une etape de routage : la ligne `referrals` garde toujours la
 * destination metier reelle (Echographie, Laboratoire...), jamais la caisse.
 */
class RouteThroughCaisse
{
    /**
     * Le couple (service ou la visite attend, destination mise en attente).
     *
     * Si aucune caisse ne correspond — cas d'un deploiement qui n'en aurait
     * pas — la destination reste directe plutot que de bloquer le patient.
     *
     * @return array{0: Service, 1: Service|null}
     */
    public function resolve(Service $destination): array
    {
        if ($destination->isCaisse()) {
            return [$destination, null];
        }

        $caisse = Service::caisseFor($destination);

        return $caisse ? [$caisse, $destination] : [$destination, null];
    }
}
