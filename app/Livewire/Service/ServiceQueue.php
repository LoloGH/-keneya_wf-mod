<?php

namespace App\Livewire\Service;

use App\Actions\CallNextPatient;
use App\Actions\CloseVisit;
use App\Actions\SendReferral;
use App\Livewire\Service\Concerns\ScopedToOwnService;
use App\Models\Service;
use App\Models\Visit;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * File d'attente du service courant : appeler le suivant, renvoyer vers un
 * autre service, cloturer le dossier.
 *
 * Tout se passe dans l'interface /service : le medecin ne quitte jamais son
 * ecran unique.
 */
class ServiceQueue extends Component
{
    use ScopedToOwnService;

    /** Visite selectionnee pour un renvoi. */
    public ?int $referringVisitId = null;

    public ?int $toServiceId = null;

    public string $instructions = '';

    #[On('service-change')]
    public function handleServiceChange(int $serviceId): void
    {
        $this->onServiceChanged($serviceId);
    }

    #[On('file-mise-a-jour')]
    public function refreshQueue(): void
    {
        // Un nouveau rendu suffit.
    }

    protected function resetServiceState(): void
    {
        $this->cancelReferral();
    }

    public function callNext(CallNextPatient $action): void
    {
        $visit = $action->execute($this->service(), $this->currentDoctor());

        session()->flash(
            'service.status',
            $visit
                ? sprintf('Ticket n° %d appele : %s (%s).', $visit->token, $visit->patient->name, $visit->patient->patient_code)
                : 'Aucun patient en attente dans cette file.'
        );

        $this->dispatch('file-mise-a-jour');
    }

    public function startReferral(int $visitId): void
    {
        $this->referringVisitId = $visitId;
        $this->toServiceId = null;
        $this->instructions = '';
        $this->resetValidation();
    }

    public function cancelReferral(): void
    {
        $this->reset(['referringVisitId', 'toServiceId', 'instructions']);
        $this->resetValidation();
    }

    public function sendReferral(SendReferral $action): void
    {
        $this->validate([
            'referringVisitId' => ['required', 'integer', 'exists:visits,id'],
            'toServiceId' => ['required', 'integer', 'exists:services,id', 'different:serviceId'],
            'instructions' => ['required', 'string', 'min:3', 'max:2000'],
        ], attributes: [
            'toServiceId' => 'service destinataire',
            'instructions' => 'instructions',
        ]);

        // La visite doit se trouver dans la file de ce service : on ne renvoie
        // pas un patient dont on n'a pas la charge.
        $visit = Visit::where('service_id', $this->serviceId)->findOrFail($this->referringVisitId);
        $toService = Service::findOrFail($this->toServiceId);

        try {
            $action->execute(
                visit: $visit,
                fromDoctor: $this->currentDoctor(),
                toService: $toService,
                instructions: $this->instructions,
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['toServiceId' => $e->getMessage()]);
        }

        session()->flash('service.status', sprintf(
            '%s a ete envoye(e) vers %s.',
            $visit->patient->name,
            $toService->name,
        ));

        $this->cancelReferral();
        $this->dispatch('file-mise-a-jour');
    }

    /**
     * Cloture de l'episode de soins. Bloquee tant qu'un renvoi attend son
     * resultat — le message le dit explicitement plutot que de griser un
     * bouton sans expliquer pourquoi.
     */
    public function closeVisit(int $visitId, CloseVisit $action): void
    {
        $visit = Visit::where('service_id', $this->serviceId)->findOrFail($visitId);

        try {
            $action->execute($visit, $this->currentDoctor());
        } catch (InvalidArgumentException $e) {
            session()->flash('service.error', $e->getMessage());
            $this->dispatch('file-mise-a-jour');

            return;
        }

        session()->flash('service.status', sprintf(
            'Dossier de %s cloture.',
            $visit->patient->name,
        ));

        $this->dispatch('file-mise-a-jour');
    }

    public function showRecord(int $patientId): void
    {
        // Le dossier s'ouvre dans un panneau de cette meme interface.
        $this->dispatch('afficher-dossier', patientId: $patientId);
    }

    private function service(): Service
    {
        return Service::findOrFail($this->serviceId);
    }

    public function render(): View
    {
        $queue = Visit::query()
            ->with(['patient', 'referrals'])
            ->inTodaysQueue($this->serviceId)
            ->orderByRaw("CASE status WHEN 'waiting' THEN 0 WHEN 'called' THEN 1 ELSE 2 END")
            ->orderBy('token')
            ->get();

        return view('livewire.service.service-queue', [
            'queue' => $queue,
            'service' => $this->service(),
            'otherServices' => Service::careServices()->where('id', '!=', $this->serviceId)
                ->with('doctors.user')
                ->orderBy('name')
                ->get(),
        ]);
    }
}
