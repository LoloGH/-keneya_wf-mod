<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

/**
 * Attribution des numeros de ticket (tokens) par service et par journee.
 *
 * La file repart a 1 chaque matin : le token affiche en salle d'attente doit
 * rester court et lisible.
 */
class TokenAllocator
{
    /**
     * Prochain token disponible dans la file du service, pour la journee en cours.
     * Verrouille les lignes du jour pour eviter deux tickets identiques quand
     * deux postes enregistrent en meme temps.
     */
    public function next(Service|int $service): int
    {
        $serviceId = $service instanceof Service ? $service->getKey() : $service;

        $query = Patient::query()->inTodaysQueue($serviceId);

        if (DB::connection()->getDriverName() !== 'sqlite') {
            $query->lockForUpdate();
        }

        return (int) $query->max('token') + 1;
    }
}
