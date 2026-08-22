<?php

namespace App\Livewire\Service;

use App\Actions\CallNextPatient;
use App\Actions\SendReferral;
use App\Livewire\Service\Concerns\ScopedToOwnService;
use App\Models\Patient;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * File d'attente du service courant, avec l'action « appeler le suivant » et
 * l'envoi d'un patient vers un autre service.
 *
 * Tout se passe dans l'interface /service : le medecin ne quitte jamais son
 * ecran unique.
 */
class ServiceQueue extends Component
{
    use ScopedToOwnService;

    /** Patient selectionne pour un renvoi. */
    public ?int $referringPatientId = null;

    public ?int $toServiceId = null;

    public string $instructions = '';

    #[On('service-change')]
    public function handleServiceChange(int $serviceId): void
    {
        $this->onServiceChanged($serviceId);
    }

    protected function resetServiceState(): void
    {
        $this->cancelReferral();
    }

    public function callNext(CallNextPatient $action): void
    {
        $patient = $action->execute($this->service(), $this->currentDoctor());

        session()->flash(
            'service.status',
            $patient
                ? sprintf('Ticket n° %d appele : %s (%s).', $patient->token, $patient->name, $patient->patient_code)
                : 'Aucun patient en attente dans cette file.'
        );

        $this->dispatch('file-mise-a-jour');
    }

    public function startReferral(int $patientId): void
    {
        $this->referringPatientId = $patientId;
        $this->toServiceId = null;
        $this->instructions = '';
        $this->resetValidation();
    }

    public function cancelReferral(): void
    {
        $this->reset(['referringPatientId', 'toServiceId', 'instructions']);
        $this->resetValidation();
    }

    public function sendReferral(SendReferral $action): void
    {
        $this->validate([
            'referringPatientId' => ['required', 'integer', 'exists:patients,id'],
            'toServiceId' => ['required', 'integer', 'exists:services,id', 'different:serviceId'],
            'instructions' => ['required', 'string', 'min:3', 'max:2000'],
        ], attributes: [
            'toServiceId' => 'service destinataire',
            'instructions' => 'instructions',
        ]);

        // Le patient doit se trouver dans la file de ce service : on ne renvoie
        // pas un patient dont on n'a pas la charge.
        $patient = Patient::where('service_id', $this->serviceId)
            ->findOrFail($this->referringPatientId);

        $toService = Service::findOrFail($this->toServiceId);

        try {
            $action->execute(
                patient: $patient,
                fromDoctor: $this->currentDoctor(),
                toService: $toService,
                instructions: $this->instructions,
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['toServiceId' => $e->getMessage()]);
        }

        session()->flash('service.status', sprintf(
            '%s a ete envoye(e) vers %s.',
            $patient->name,
            $toService->name,
        ));

        $this->cancelReferral();
        $this->dispatch('file-mise-a-jour');
    }

    public function showHistory(int $patientId): void
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
        $queue = Patient::query()
            ->inTodaysQueue($this->serviceId)
            ->orderByRaw("CASE WHEN status = 'waiting' THEN 0 ELSE 1 END")
            ->orderBy('token')
            ->get();

        return view('livewire.service.service-queue', [
            'queue' => $queue,
            'service' => $this->service(),
            'otherServices' => Service::where('id', '!=', $this->serviceId)
                ->with('doctors.user')
                ->orderBy('name')
                ->get(),
        ]);
    }
}
