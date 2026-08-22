<?php

namespace App\Services;

use App\Models\Service;
use App\Models\Visit;
use App\Models\Visitor;
use Illuminate\Support\Facades\DB;

/**
 * Attribution des numeros de ticket, par service et par journee.
 *
 * Patients et visiteurs tirent dans la meme sequence : sur l'ecran de salle
 * d'attente, deux personnes ne doivent jamais voir le meme numero pour le
 * meme service.
 *
 * Les files repartent a 1 chaque matin — le numero affiche doit rester court
 * et lisible de loin.
 */
class TokenAllocator
{
    public function next(Service|int $service): int
    {
        $serviceId = $service instanceof Service ? $service->getKey() : $service;

        $visits = Visit::query()->inTodaysQueue($serviceId);
        $visitors = Visitor::query()
            ->where('service_id', $serviceId)
            ->whereDate('created_at', today());

        if (DB::connection()->getDriverName() !== 'sqlite') {
            $visits->lockForUpdate();
            $visitors->lockForUpdate();
        }

        return max((int) $visits->max('token'), (int) $visitors->max('token')) + 1;
    }
}
