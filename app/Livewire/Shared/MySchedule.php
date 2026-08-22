<?php

namespace App\Livewire\Shared;

use App\Models\Schedule;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Planning personnel, en lecture seule (addendum v2, point 8).
 *
 * Le meme composant sert dans /service et dans /reception : il n'expose que
 * les creneaux de l'utilisateur connecte, jamais ceux d'un collegue. La
 * gestion des plannings reste exclusivement dans /admin.
 */
class MySchedule extends Component
{
    /** Nombre de jours affiches a partir d'aujourd'hui. */
    public int $days = 14;

    public function render(): View
    {
        $schedules = Schedule::query()
            ->with('service')
            ->where('user_id', Auth::id())
            ->whereBetween('date', [today(), today()->addDays($this->days)])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return view('livewire.shared.my-schedule', [
            'schedules' => $schedules,
        ]);
    }
}
