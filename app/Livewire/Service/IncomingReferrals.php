<?php

namespace App\Livewire\Service;

use App\Actions\Dme\CompleteExaminationReferral;
use App\Actions\Dme\StoreMedicalDocument;
use App\Livewire\Service\Concerns\ScopedToOwnService;
use App\Models\Referral;
use Illuminate\Contracts\View\View;
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
 * Depuis la v3.3.1, un renvoi vers un plateau technique arrive avec la demande
 * d'examen que le medecin a posee en envoyant le patient : le technicien lit
 * ce qu'on lui demande sans avoir a le deviner ni a ouvrir un autre ecran.
 *
 * Son compte rendu part au **dossier medical** du patient, ou il restera, et
 * non en piece jointe d'un renvoi, qui n'est qu'un mouvement du parcours. La
 * demande cesse d'etre en attente, et le patient retourne dans la file du
 * medecin qui l'a envoye.
 */
class IncomingReferrals extends Component
{
    use ScopedToOwnService, WithFileUploads;

    public ?int $answeringReferralId = null;

    public string $resultText = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $files = [];

    /** Titre du compte rendu au dossier. Facultatif : le module en pose un. */
    public string $documentTitle = '';

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
        $this->documentTitle = '';
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->reset(['answeringReferralId', 'resultText', 'files', 'documentTitle']);
        $this->resetValidation();
    }

    public function submitResult(CompleteExaminationReferral $action, StoreMedicalDocument $documents): void
    {
        $this->validate([
            'answeringReferralId' => ['required', 'integer', 'exists:referrals,id'],
            'resultText' => ['required', 'string', 'min:3', 'max:5000'],
            'documentTitle' => ['nullable', 'string', 'max:255'],
            'files' => ['array', 'max:5'],
            'files.*' => [
                'file',
                'max:'.$documents->maxSizeKb(),
                'mimes:'.implode(',', $documents->allowedExtensions()),
            ],
        ], attributes: [
            'resultText' => 'resultat',
            'documentTitle' => 'titre du compte rendu',
            'files' => 'comptes rendus',
        ]);

        $referral = Referral::with(['patient', 'visit'])
            ->where('to_service_id', $this->serviceId)
            ->findOrFail($this->answeringReferralId);

        try {
            $action->execute(
                referral: $referral,
                completedBy: $this->currentAgent(),
                resultText: $this->resultText,
                fichiers: $this->files,
                titre: $this->documentTitle ?: null,
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['resultText' => $e->getMessage()]);
        }

        session()->flash('service.status', sprintf(
            'Resultat verse au dossier de %s et transmis au prescripteur.',
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
            ->with([
                'patient', 'fromService', 'fromDoctor.user', 'fromStaffMember.user', 'visit',
                // La demande arrive avec le patient : elle se lit ici, pas
                // dans un autre ecran.
                'labOrder.items', 'imagingOrder',
            ])
            ->where('to_service_id', $this->serviceId)
            ->where('status', Referral::STATUS_PENDING)
            ->orderBy('created_at')
            ->get();

        return view('livewire.service.incoming-referrals', [
            'pending' => $pending,
        ]);
    }
}
