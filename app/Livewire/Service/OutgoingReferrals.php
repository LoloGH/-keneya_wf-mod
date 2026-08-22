<?php

namespace App\Livewire\Service;

use App\Actions\CloseReferral;
use App\Livewire\Service\Concerns\ScopedToOwnService;
use App\Models\Referral;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Panneau « Mes renvois » du medecin prescripteur.
 *
 * « Resultats recus » ne montre que les renvois dont le resultat est arrive et
 * que le prescripteur n'a pas encore clotures : une fois la boucle fermee, le
 * renvoi disparait de ce panneau mais reste dans l'historique du patient.
 *
 * Rafraichi par wire:poll — pas de WebSocket dans cette phase.
 */
class OutgoingReferrals extends Component
{
    use ScopedToOwnService;

    #[On('service-change')]
    public function handleServiceChange(int $serviceId): void
    {
        $this->onServiceChanged($serviceId);
    }

    #[On('file-mise-a-jour')]
    public function refreshPanel(): void
    {
        // Un nouveau rendu suffit.
    }

    public function closeReferral(int $referralId, CloseReferral $action): void
    {
        $doctor = $this->currentDoctor();

        $referral = Referral::where('from_doctor_id', $doctor->getKey())->findOrFail($referralId);

        try {
            $action->execute($referral, $doctor);
        } catch (InvalidArgumentException $e) {
            session()->flash('service.error', $e->getMessage());

            return;
        }

        session()->flash('service.status', 'Renvoi cloture.');
        $this->dispatch('file-mise-a-jour');
    }

    public function showRecord(int $patientId): void
    {
        $this->dispatch('afficher-dossier', patientId: $patientId);
    }

    public function render(): View
    {
        $base = Referral::query()
            ->with(['patient', 'toService', 'completedByDoctor.user', 'attachments'])
            ->where('from_doctor_id', $this->currentDoctor()->getKey());

        return view('livewire.service.outgoing-referrals', [
            'pending' => (clone $base)
                ->where('status', Referral::STATUS_PENDING)
                ->orderByDesc('created_at')
                ->get(),
            'results' => (clone $base)
                ->where('status', Referral::STATUS_DONE)
                ->orderByDesc('completed_at')
                ->get(),
        ]);
    }
}
