<?php

namespace App\Livewire\Service;

use App\Livewire\Service\Concerns\ScopedToOwnService;
use App\Models\Referral;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Panneau « Renvois envoyes / Resultats recus » du medecin prescripteur.
 *
 * Rafraichi par wire:poll : pas de WebSocket dans cette phase, le resultat
 * apparait au prochain cycle de polling.
 */
class OutgoingReferrals extends Component
{
    use ScopedToOwnService;

    #[On('service-change')]
    public function handleServiceChange(int $serviceId): void
    {
        $this->onServiceChanged($serviceId);
    }

    public function showHistory(int $patientId): void
    {
        $this->dispatch('afficher-dossier', patientId: $patientId);
    }

    public function render(): View
    {
        $base = Referral::query()
            ->with(['patient', 'toService', 'completedByDoctor.user'])
            ->where('from_doctor_id', $this->currentDoctor()->getKey());

        return view('livewire.service.outgoing-referrals', [
            'pending' => (clone $base)
                ->where('status', Referral::STATUS_PENDING)
                ->orderByDesc('created_at')
                ->get(),
            'results' => (clone $base)
                ->where('status', Referral::STATUS_DONE)
                ->orderByDesc('completed_at')
                ->limit(20)
                ->get(),
        ]);
    }
}
