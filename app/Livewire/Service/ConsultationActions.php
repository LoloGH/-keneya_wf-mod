<?php

namespace App\Livewire\Service;

use App\Actions\CreatePrescription;
use App\Actions\RecordPayment;
use App\Actions\ScheduleAppointment;
use App\Livewire\Service\Concerns\ScopedToOwnService;
use App\Models\Payment;
use App\Models\Visit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Fin de consultation, au service : encaissement de l'acte (« Caisse
 * Services »), ordonnance et prochain rendez-vous.
 *
 * Les trois portent sur la visite en cours du patient appele — d'ou un seul
 * composant plutot que trois, pour ne pas multiplier les selecteurs de patient
 * dans la meme interface.
 */
class ConsultationActions extends Component
{
    use ScopedToOwnService;

    public ?int $visitId = null;

    /** Onglet actif : caisse, ordonnance ou rendez-vous. */
    public string $tab = 'caisse';

    public ?int $amount = null;

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
        $this->reset(['visitId', 'amount', 'prescription', 'appointmentAt']);
        $this->resetValidation();
    }

    public function selectTab(string $tab): void
    {
        $this->tab = in_array($tab, ['caisse', 'ordonnance', 'rendez-vous'], true) ? $tab : 'caisse';
        $this->resetValidation();
    }

    public function recordPayment(RecordPayment $action): void
    {
        $this->validate([
            'visitId' => ['required', 'integer', 'exists:visits,id'],
            'amount' => ['required', 'integer', 'min:1', 'max:9999999999'],
        ], attributes: ['visitId' => 'patient', 'amount' => 'montant']);

        $visit = $this->visitInThisService();

        try {
            $payment = $action->execute(
                visit: $visit,
                recordedBy: Auth::user(),
                type: Payment::TYPE_SERVICE,
                amount: (int) $this->amount,
                serviceId: $this->serviceId,
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        session()->flash('service.status', sprintf(
            'Encaissement de %s enregistre pour %s.',
            $payment->formattedAmount(),
            $visit->patient->name,
        ));

        $this->reset('amount');
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
            'recentPayments' => Payment::query()
                ->with(['patient', 'service'])
                ->where('service_id', $this->serviceId)
                ->where('type', Payment::TYPE_SERVICE)
                ->whereDate('created_at', today())
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
        ]);
    }
}
