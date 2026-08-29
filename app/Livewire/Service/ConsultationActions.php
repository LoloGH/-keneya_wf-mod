<?php

namespace App\Livewire\Service;

use App\Actions\CreatePrescription;
use App\Actions\RecordConsultationConclusion;
use App\Actions\ScheduleAppointment;
use App\Livewire\Concerns\RequiresCapability;
use App\Livewire\Service\Concerns\ScopedToOwnService;
use App\Models\StaffType;
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
    use RequiresCapability, ScopedToOwnService;

    public ?int $visitId = null;

    /** Onglet actif : conclusion, ordonnance ou rendez-vous. */
    public string $tab = 'conclusion';

    public string $conclusion = '';

    /**
     * Lignes de l'ordonnance en cours de saisie (v3.2.6).
     *
     * Une ligne vide est ouverte d'emblee : le medecin n'a pas a cliquer pour
     * commencer a ecrire.
     *
     * @var array<int, array{medicament: string, posologie: string, duree: string}>
     */
    public array $prescriptionLines = [self::LIGNE_VIDE];

    /** Garde-fou : une ordonnance n'a pas cinquante lignes. */
    public const MAX_LIGNES = 20;

    private const LIGNE_VIDE = ['medicament' => '', 'posologie' => '', 'duree' => ''];

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
        $this->reset(['visitId', 'conclusion', 'appointmentAt']);
        $this->prescriptionLines = [self::LIGNE_VIDE];
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
        $this->assertCapability(StaffType::CAP_PRESCRIBE);

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

    /** Ouvre une ligne de plus, sous la derniere. */
    public function addPrescriptionLine(): void
    {
        if (count($this->prescriptionLines) >= self::MAX_LIGNES) {
            return;
        }

        $this->prescriptionLines[] = self::LIGNE_VIDE;
    }

    /**
     * Retire une ligne. La derniere ne se retire pas : le formulaire garderait
     * une zone de saisie vide sans champ.
     */
    public function removePrescriptionLine(int $index): void
    {
        if (count($this->prescriptionLines) <= 1) {
            $this->prescriptionLines = [self::LIGNE_VIDE];

            return;
        }

        unset($this->prescriptionLines[$index]);
        $this->prescriptionLines = array_values($this->prescriptionLines);
        $this->resetValidation();
    }

    public function savePrescription(CreatePrescription $action): void
    {
        $this->assertCapability(StaffType::CAP_PRESCRIBE);

        $this->validate([
            'visitId' => ['required', 'integer', 'exists:visits,id'],
            'prescriptionLines' => ['array', 'max:'.self::MAX_LIGNES],
            'prescriptionLines.*.medicament' => ['nullable', 'string', 'max:255'],
            'prescriptionLines.*.posologie' => ['nullable', 'string', 'max:255'],
            'prescriptionLines.*.duree' => ['nullable', 'string', 'max:120'],
        ], attributes: ['visitId' => 'patient']);

        $visit = $this->visitInThisService();

        try {
            $prescription = $action->execute($visit, $this->currentDoctor(), $this->prescriptionLines);
        } catch (InvalidArgumentException $e) {
            // Le medicament fait la ligne : le message le dit plutot que de
            // signaler un champ vide parmi vingt.
            $this->addError('prescriptionLines', $e->getMessage());

            return;
        }

        session()->flash('service.status', sprintf(
            'Ordonnance enregistree pour %s : %d ligne(s).',
            $visit->patient->name,
            count($prescription->lignes()),
        ));

        $this->prescriptionLines = [self::LIGNE_VIDE];
        $this->dispatch('ordonnance-creee', prescriptionId: $prescription->getKey());
        $this->dispatch('file-mise-a-jour');
    }

    public function saveAppointment(ScheduleAppointment $action): void
    {
        $this->assertCapability(StaffType::CAP_SCHEDULE_APPOINTMENT);

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
