<?php

namespace App\Livewire\Service;

use App\Actions\CompleteReferral;
use App\Actions\StoreAttachment;
use App\Livewire\Service\Concerns\ScopedToOwnService;
use App\Models\Attachment;
use App\Models\Referral;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Panneau « Renvois en attente » : ce que le service du praticien connecte
 * doit traiter, filtre sur son propre service_id.
 *
 * Le resultat peut etre accompagne d'une piece jointe (image d'echographie,
 * PDF de laboratoire).
 */
class IncomingReferrals extends Component
{
    use ScopedToOwnService, WithFileUploads;

    public ?int $answeringReferralId = null;

    public string $resultText = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $files = [];

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

    protected function resetServiceState(): void
    {
        $this->cancel();
    }

    public function startAnswer(int $referralId): void
    {
        $this->answeringReferralId = $referralId;
        $this->resultText = '';
        $this->files = [];
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->reset(['answeringReferralId', 'resultText', 'files']);
        $this->resetValidation();
    }

    public function submitResult(CompleteReferral $action, StoreAttachment $attachments): void
    {
        $this->validate([
            'answeringReferralId' => ['required', 'integer', 'exists:referrals,id'],
            'resultText' => ['required', 'string', 'min:3', 'max:5000'],
            'files' => ['array', 'max:5'],
            'files.*' => [
                'file',
                'max:'.Attachment::MAX_SIZE_KB,
                'mimes:'.implode(',', Attachment::ALLOWED_EXTENSIONS),
            ],
        ], attributes: ['resultText' => 'resultat', 'files' => 'pieces jointes']);

        $referral = Referral::with(['patient', 'visit'])
            ->where('to_service_id', $this->serviceId)
            ->findOrFail($this->answeringReferralId);

        try {
            $action->execute(
                referral: $referral,
                completedBy: $this->currentDoctor(),
                resultText: $this->resultText,
            );

            foreach ($this->files as $file) {
                $attachments->execute(
                    file: $file,
                    visit: $referral->visit,
                    uploadedBy: Auth::user(),
                    referral: $referral,
                );
            }
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

    public function showRecord(int $patientId): void
    {
        $this->dispatch('afficher-dossier', patientId: $patientId);
    }

    public function render(): View
    {
        $pending = Referral::query()
            ->with(['patient', 'fromService', 'fromDoctor.user', 'visit'])
            ->where('to_service_id', $this->serviceId)
            ->where('status', Referral::STATUS_PENDING)
            ->orderBy('created_at')
            ->get();

        return view('livewire.service.incoming-referrals', [
            'pending' => $pending,
        ]);
    }
}
