<?php

namespace App\Livewire\Service;

use App\Actions\CallNextPatient;
use App\Actions\CloseVisit;
use App\Actions\Dme\ReferForExamination;
use App\Livewire\Concerns\RequestsExamination;
use App\Livewire\Concerns\RequiresCapability;
use App\Livewire\Service\Concerns\ScopedToOwnService;
use App\Models\BillableItem;
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
    use RequestsExamination, RequiresCapability, ScopedToOwnService;

    /** Visite selectionnee pour un renvoi. */
    public ?int $referringVisitId = null;

    public ?int $toServiceId = null;

    /**
     * L'acte precis demande, parmi les tarifs du service destinataire
     * (v3.2.8, point 3) : « Echographie abdominale » plutot que « Echographie ».
     * Il voyage avec la visite jusqu'a la caisse.
     */
    public ?int $billableItemId = null;

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
        $this->billableItemId = null;
        $this->resetExamination();
        $this->instructions = '';
        $this->resetValidation();
    }

    /**
     * Changer de service destinataire change la liste des actes proposes : un
     * acte de radiologie n'a rien a faire dans un renvoi vers le laboratoire.
     */
    public function updatedToServiceId(): void
    {
        $this->billableItemId = null;
        // Le formulaire de demande suit la destination : changer de service
        // doit repartir d'une demande vierge, jamais de celle qu'on
        // s'appretait a adresser ailleurs.
        $this->resetExamination();
    }

    public function cancelReferral(): void
    {
        $this->reset(['referringVisitId', 'toServiceId', 'billableItemId', 'instructions']);
        $this->resetExamination();
        $this->resetValidation();
    }

    /**
     * Le renvoi emporte la demande d'examen quand la destination en realise
     * (v3.3.1) : le technicien recoit le patient **et** ce qu'on lui demande.
     */
    public function sendReferral(ReferForExamination $action): void
    {
        // Le service est lu avant la validation : c'est lui qui decide quelles
        // regles s'appliquent, la demande n'ayant pas la meme forme selon
        // qu'on adresse des analyses ou une imagerie.
        $toService = Service::with('serviceKind')->find($this->toServiceId);

        $this->validate([
            'referringVisitId' => ['required', 'integer', 'exists:visits,id'],
            'toServiceId' => ['required', 'integer', 'exists:services,id', 'different:serviceId'],
            'billableItemId' => ['nullable', 'integer', 'exists:billable_items,id'],
            'instructions' => ['required', 'string', 'min:3', 'max:2000'],
            ...$this->examinationRules($toService),
        ], attributes: [
            'toServiceId' => 'service destinataire',
            'billableItemId' => 'acte demande',
            'instructions' => 'instructions',
            'examPriority' => 'priorite',
            'examIndication' => 'indication',
            'examModality' => 'modalite',
            'examBodySite' => 'region examinee',
        ]);

        if ($capacite = $this->examinationCapability($toService)) {
            $this->assertCapability($capacite);
        }

        if (! $this->examinationIsComplete($toService)) {
            return;
        }

        // La visite doit se trouver dans la file de ce service : on ne renvoie
        // pas un patient dont on n'a pas la charge.
        $visit = Visit::where('service_id', $this->serviceId)->findOrFail($this->referringVisitId);

        try {
            $action->execute(
                visit: $visit,
                fromDoctor: $this->currentAgent(),
                toService: $toService,
                instructions: $this->instructions,
                billableItem: $this->billableItemId ? BillableItem::find($this->billableItemId) : null,
                examen: $this->examinationPayload($toService),
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
     * resultat : le message le dit explicitement plutot que de griser un
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
            // Les actes du service destinataire choisi. Vide tant qu'aucun
            // service n'est selectionne : la liste n'aurait aucun sens.
            'actes' => $this->toServiceId
                ? BillableItem::forService((int) $this->toServiceId)->orderBy('name')->get()
                : collect(),
            // Ce que realise la destination choisie : c'est elle qui fait
            // apparaitre le formulaire de demande, et lequel.
            'examKind' => $this->toServiceId
                ? Service::with('serviceKind')->find($this->toServiceId)?->examKind()
                : null,
        ]);
    }
}
