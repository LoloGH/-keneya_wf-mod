<?php

namespace App\Livewire\Board;

use App\Models\Service;
use App\Models\Visit;
use App\Models\Visitor;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Ecran de salle d'attente : tokens en cours et a venir, par service.
 *
 * Patients et visiteurs partagent la meme sequence de numeros par service :
 * deux personnes ne voient jamais le meme numero affiche.
 *
 * Utilise a deux endroits : sur le moniteur public (/board, sans connexion) et
 * dans l'interface de la receptionniste, qui doit pouvoir le surveiller depuis
 * son poste. Ce n'est pas une interface « de role », d'ou l'absence de tout
 * controle de role ici.
 */
class WaitingBoard extends Component
{
    /** Affichage plein ecran pour le moniteur mural. */
    public bool $fullscreen = false;

    public function render(): View
    {
        $rows = Service::query()
            ->orderBy('name')
            ->get()
            ->map(function (Service $service) {
                $called = Visit::query()
                    ->inTodaysQueue($service->getKey())
                    ->where('status', Visit::STATUS_CALLED)
                    ->orderByDesc('updated_at')
                    ->first();

                $waiting = Visit::query()
                    ->inTodaysQueue($service->getKey())
                    ->where('status', Visit::STATUS_WAITING)
                    ->orderBy('token')
                    ->pluck('token');

                // Le personnel doit savoir qui orienter vers qui : le ticket
                // d'un visiteur porte le nom du patient visite.
                $visitors = Visitor::query()
                    ->with('patient')
                    ->where('service_id', $service->getKey())
                    ->whereDate('created_at', today())
                    ->whereNotNull('token')
                    ->orderBy('token')
                    ->get();

                $visitorTokens = $visitors->pluck('token');

                // Les deux files partagent la meme sequence : on les fusionne
                // pour afficher un ordre d'appel unique et lisible.
                $upcoming = $waiting->concat($visitorTokens)->sort()->values();

                return [
                    'service' => $service,
                    'current' => $called?->token,
                    'next' => $upcoming->take(3),
                    'waiting_count' => $upcoming->count(),
                    'visitors' => $visitors->take(3),
                ];
            });

        return view('livewire.board.waiting-board', [
            'rows' => $rows,
        ]);
    }
}
