<?php

namespace App\Livewire\Board;

use App\Models\Patient;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Ecran de salle d'attente : tokens en cours et a venir, par service.
 *
 * Utilise a deux endroits — sur le moniteur public (/board, sans connexion) et
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
        $services = Service::query()
            ->orderBy('name')
            ->get()
            ->map(function (Service $service) {
                $called = Patient::query()
                    ->inTodaysQueue($service->getKey())
                    ->where('status', Patient::STATUS_CALLED)
                    ->orderByDesc('updated_at')
                    ->first();

                $waiting = Patient::query()
                    ->inTodaysQueue($service->getKey())
                    ->where('status', Patient::STATUS_WAITING)
                    ->orderBy('token')
                    ->get();

                return [
                    'service' => $service,
                    'current' => $called,
                    'next' => $waiting->take(3),
                    'waiting_count' => $waiting->count(),
                ];
            });

        return view('livewire.board.waiting-board', [
            'rows' => $services,
        ]);
    }
}
