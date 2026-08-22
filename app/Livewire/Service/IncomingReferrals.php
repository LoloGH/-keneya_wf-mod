<?php

namespace App\Livewire\Service;

use App\Actions\CompleteReferral;
use App\Livewire\Service\Concerns\ScopedToOwnService;
use App\Models\Referral;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Panneau « Renvois en attente » : ce que le service du praticien connecte doit
 * traiter, filtre sur son propre service_id.
 */
class IncomingReferrals extends Component
{
    use ScopedToOwnService;

    public ?int $answeringReferralId = null;

    public string $resultText = '';

    #[On('service-change')]
    public function handleServiceChange(int $serviceId): void
    {
        $this->onServiceChanged($serviceId);
    }

    protected function resetServiceState(): void
    {
        $this->cancel();
    }

    public function startAnswer(int $referralId): void
    {
        $this->answeringReferralId = $referralId;
        $this->resultText = '';
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->reset(['answeringReferralId', 'resultText']);
        $this->resetValidation();
    }

    public function submitResult(CompleteReferral $action): void
    {
        $this->validate([
            'answeringReferralId' => ['required', 'integer', 'exists:referrals,id'],
            'resultText' => ['required', 'string', 'min:3', 'max:5000'],
        ], attributes: ['resultText' => 'resultat']);

        $referral = Referral::with('patient')
            ->where('to_service_id', $this->serviceId)
            ->findOrFail($this->answeringReferralId);

        try {
            $action->execute(
                referral: $referral,
                completedBy: $this->currentDoctor(),
                resultText: $this->resultText,
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['resultText' => $e->getMessage()]);
        }

        session()->flash('service.status', sprintf(
            'Resultat transmis au prescripteur pour %s.',
            $referral->patient->name,
        ));

        $this->cancel();
        $this->dispatch('file-mise-a-jour');
    }

    public function showHistory(int $patientId): void
    {
        $this->dispatch('afficher-dossier', patientId: $patientId);
    }

    public function render(): View
    {
        $pending = Referral::query()
            ->with(['patient', 'fromService', 'fromDoctor.user'])
            ->where('to_service_id', $this->serviceId)
            ->where('status', Referral::STATUS_PENDING)
            ->orderBy('created_at')
            ->get();

        return view('livewire.service.incoming-referrals', [
            'pending' => $pending,
        ]);
    }
}
