<?php

namespace App\Livewire\Staff;

use App\Actions\Dme\CompleteExaminationReferral;
use App\Actions\Dme\StoreMedicalDocument;
use App\Models\Referral;
use App\Models\StaffMember;
use App\Models\StaffType;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Renvois recus par un type de personnel generique (v3.2.1, point 10).
 *
 * Meme Action que cote medecin : le compte rendu part au dossier medical du
 * patient, la demande d'examen cesse d'etre en attente, et le patient repart
 * dans la file du prescripteur — sans repasser par la caisse.
 *
 * C'est ici qu'arrive le technicien du plateau technique : le renvoi lui
 * montre ce qu'on lui demande (v3.3.1), et il verse son compte rendu au
 * dossier sans changer d'ecran.
 */
class StaffIncomingReferrals extends Component
{
    use WithFileUploads;

    public ?int $answeringReferralId = null;

    public string $resultText = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $files = [];

    /** Titre du compte rendu au dossier. Facultatif : le module en pose un. */
    public string $documentTitle = '';

    /**
     * La capacite est verifiee des le montage, pas seulement a l'action : un
     * composant qu'on n'a pas le droit d'utiliser ne doit meme pas s'afficher.
     */
    public function mount(): void
    {
        $this->member();
    }

    #[On('file-mise-a-jour')]
    public function refreshPanel(): void
    {
        // Un nouveau rendu suffit.
    }

    private function member(): StaffMember
    {
        $member = Auth::user()?->staffMember?->load(['staffType', 'service']);

        abort_unless($member && $member->service_id, 403, "Aucun service n'est rattache a votre compte.");
        abort_unless($member->staffType->can(StaffType::CAP_RECEIVE_REFERRAL), 403, 'Cette action ne releve pas de votre fonction.');

        return $member;
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
        $member = $this->member();

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
            'answeringReferralId' => 'renvoi',
            'resultText' => 'resultat',
            'documentTitle' => 'titre du compte rendu',
            'files' => 'comptes rendus',
        ]);

        // Un renvoi adresse a un autre service n'existe pas de mon point de vue.
        $referral = Referral::with('visit')
            ->where('to_service_id', $member->service_id)
            ->findOrFail($this->answeringReferralId);

        try {
            $action->execute(
                referral: $referral,
                completedBy: $member,
                resultText: $this->resultText,
                fichiers: $this->files,
                titre: $this->documentTitle ?: null,
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['resultText' => $e->getMessage()]);
        }

        session()->flash('staff.status', 'Resultat verse au dossier : le patient repart vers le service prescripteur.');

        $this->cancel();
        $this->dispatch('file-mise-a-jour');
    }

    public function render(): View
    {
        $member = $this->member();

        return view('livewire.staff.staff-incoming-referrals', [
            'referrals' => Referral::query()
                ->with([
                    'patient', 'fromService', 'fromDoctor.user', 'fromStaffMember.user',
                    // La demande arrive avec le patient : elle se lit ici.
                    'labOrder.items', 'imagingOrder',
                ])
                ->where('to_service_id', $member->service_id)
                ->where('status', Referral::STATUS_PENDING)
                ->orderBy('id')
                ->get(),
        ]);
    }
}
