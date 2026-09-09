<?php

namespace App\Livewire\Staff;

use App\Models\Hospitalization;
use App\Models\StaffMember;
use App\Models\StaffType;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Notes de releve vues par le personnel de garde (v3.2.3, point 4).
 *
 * Meme cloisonnement que les soins : les sejours du service de rattachement, et
 * eux seuls. La lecture reste ouverte hors garde : c'est justement en prenant
 * son poste, avant que le planning ne couvre l'heure, qu'on a besoin de lire ce
 * que l'equipe precedente a laisse.
 */
class StaffHandoffs extends Component
{
    private function member(): StaffMember
    {
        $member = Auth::user()?->staffMember?->load(['staffType', 'service']);

        abort_unless($member && $member->service_id, 403, "Aucun service n'est rattache a votre compte.");
        abort_unless($member->staffType->can(StaffType::CAP_CARE_TASKS), 403, 'Cette action ne releve pas de votre fonction.');

        return $member;
    }

    public function render(): View
    {
        $member = $this->member();

        return view('livewire.staff.staff-handoffs', [
            'service' => $member->service,
            'sejours' => Hospitalization::with(['patient', 'room'])
                ->where('service_id', $member->service_id)
                ->active()
                ->withCount('handoffNotes')
                ->orderByDesc('admitted_at')
                ->get(),
        ]);
    }
}
