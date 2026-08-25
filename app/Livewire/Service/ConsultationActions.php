<?php

namespace App\Livewire\Service;

use App\Actions\CreatePrescription;
use App\Actions\RecordConsultationConclusion;
use App\Actions\ScheduleAppointment;
use App\Livewire\Service\Concerns\ScopedToOwnService;
use App\Models\Visit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Fin de consultation, au service : conclusion, ordonnance et prochain
 * rendez-vous.
 *
 * Aucune fonction de caisse ici : dans cet hopital les medecins n'encaissent
 * jamais, tout passe par le role `cashier` et l'interface /caisse.
 *
 * Les trois actions portent sur la visite en cours du patient appele — d'ou un
 * seul composant plutot que trois, pour ne pas multiplier les selecteurs de
 * patient dans la meme interface.
 */
class ConsultationActions extends Component
{
    use ScopedToOwnService;

    public ?int $visitId = null;

    /** Onglet actif : conclusion, ordonnance ou rendez-vous. */
    public string $tab = 'conclusion';

    public string $conclusion = '';

    public string $prescription = '';

    public string $appointmentAt = '';

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
        $this->reset(['visitId', 'conclusion', 'prescription', 'appointmentAt']);
        $this->resetValidation();
    }

    public function selectTab(string $tab): void
    {
        $this->tab = in_array($tab, ['conclusion', 'ordonnance', 'rendez-vous'], true) ? $tab : 'conclusion';
        $this->resetValidation();
    }

    /**
     * Conclusion de la prise en charge — distincte de l'ordonnance, qui reste
     * dediee aux medicaments.
     */
    public function recordConclusion(RecordConsultationConclusion $action): void
    {
        $this->validate([
            'visitId' => ['required', 'integer', 'exists:visits,id'],
            'conclusion' => ['required', 'string', 'min:3', 'max:5000'],
        ], attributes: ['visitId' => 'patient', 'conclusion' => 'conclusion']);

        $visit = $this->visitInThisService();

        $action->execute($visit, $this->currentDoctor(), $this->conclusion);

        session()->flash('service.status', sprintf(
            'Conclusion enregistree pour %s.',
            $visit->patient->name,
        ));

        $this->reset('conclusion');
        $this->dispatch('file-mise-a-jour');
    }

    public function savePrescription(CreatePrescription $action): void
    {
        $this->validate([
            'visitId' => ['required', 'integer', 'exists:visits,id'],
            'prescription' => ['required', 'string', 'min:3', 'max:5000'],
        ], attributes: ['visitId' => 'patient', 'prescription' => 'ordonnance']);

        $visit = $this->visitInThisService();

        $prescription = $action->execute($visit, $this->currentDoctor(), $this->prescription);

        session()->flash('service.status', sprintf(
            'Ordonnance enregistree pour %s.',
            $visit->patient->name,
        ));

        $this->reset('prescription');
        $this->dispatch('ordonnance-creee', prescriptionId: $prescription->getKey());
        $this->dispatch('file-mise-a-jour');
    }

    public function saveAppointment(ScheduleAppointment $action): void
    {
        $this->validate([
            'visitId' => ['required', 'integer', 'exists:visits,id'],
            'appointmentAt' => ['required', 'date', 'after:now'],
        ], attributes: ['visitId' => 'patient', 'appointmentAt' => 'date du rendez-vous']);

        $visit = $this->visitInThisService();

        try {
            $action->execute($visit, $this->currentDoctor(), Carbon::parse($this->appointmentAt), $this->serviceId);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['appointmentAt' => $e->getMessage()]);
        }

        session()->flash('service.status', sprintf(
            'Rendez-vous fixe pour %s.',
            $visit->patient->name,
        ));

        $this->reset('appointmentAt');
        $this->dispatch('rendez-vous-cree');
        $this->dispatch('file-mise-a-jour');
    }

    /**
     * La visite doit appartenir au service du praticien connecte.
     */
    private function visitInThisService(): Visit
    {
        return Visit::with('patient')
            ->where('service_id', $this->serviceId)
            ->findOrFail($this->visitId);
    }

    public function render(): View
    {
        return view('livewire.service.consultation-actions', [
            'visits' => Visit::query()
                ->with('patient')
                ->inTodaysQueue($this->serviceId)
                ->where('status', Visit::STATUS_CALLED)
                ->orderBy('token')
                ->get(),
        ]);
    }
}
