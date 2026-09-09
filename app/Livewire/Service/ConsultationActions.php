<?php

namespace App\Livewire\Service;

use App\Actions\AssignVisitPathology;
use App\Actions\Dme\CreateMedicalPrescription;
use App\Actions\ScheduleAppointment;
use App\Livewire\Concerns\RequiresCapability;
use App\Livewire\Service\Concerns\ScopedToOwnService;
use App\Models\Pathology;
use App\Models\StaffType;
use App\Models\Visit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Fin de consultation, au service : ordonnance, prochain rendez-vous et
 * pathologie du passage.
 *
 * La conclusion ne s'ecrit plus ici (v3.3.1). Elle est passee au dossier
 * medical, sous « Dossier medical > Consultation », avec le motif, les
 * constantes, l'examen et les diagnostics : c'est une donnee de sante, et
 * deux champs pour un meme geste etaient surtout une occasion de se tromper
 * de place. Ce qui s'ecrivait ici n'atteignait jamais le dossier.
 *
 * Aucune fonction de caisse ici : dans cet hopital les medecins n'encaissent
 * jamais, tout passe par le role `cashier` et l'interface /caisse.
 *
 * Les trois actions portent sur la visite en cours du patient appele : d'ou un
 * seul composant plutot que trois, pour ne pas multiplier les selecteurs de
 * patient dans la meme interface.
 */
class ConsultationActions extends Component
{
    use RequiresCapability, ScopedToOwnService;

    public ?int $visitId = null;

    /** Onglet actif : pathologie, ordonnance ou rendez-vous. */
    public string $tab = 'ordonnance';

    /** Pathologie notee sur le passage. Facultative, jamais bloquante. */
    public ?int $pathologyId = null;

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
        $this->reset(['visitId', 'appointmentAt']);
        $this->prescriptionLines = [self::LIGNE_VIDE];
        $this->resetValidation();
    }

    public function selectTab(string $tab): void
    {
        $this->tab = in_array($tab, ['conclusion', 'ordonnance', 'rendez-vous'], true) ? $tab : 'ordonnance';
        $this->resetValidation();
    }

    /**
     * La pathologie notee sur le passage (v3.3.1).
     *
     * Ce qui reste de l'ancien formulaire de conclusion. La conclusion
     * elle-meme est passee au dossier medical, ou est sa place : c'est une
     * donnee de sante, et elle s'ecrit desormais sous « Dossier medical >
     * Consultation », avec le motif, les constantes et les diagnostics.
     *
     * La pathologie, elle, ne sert pas au soin mais au fonctionnement de
     * l'etablissement : s'adresser plus tard a un groupe de patients par SMS.
     * Elle serait partie avec la conclusion si l'on n'y avait pas pris garde,
     * et la diffusion aurait perdu sa seule source.
     */
    public function recordPathology(AssignVisitPathology $action): void
    {
        $this->assertCapability(StaffType::CAP_PRESCRIBE);

        $this->validate([
            'visitId' => ['required', 'integer', 'exists:visits,id'],
            'pathologyId' => ['nullable', 'integer', 'exists:pathologies,id'],
        ], attributes: [
            'visitId' => 'patient',
            'pathologyId' => 'pathologie',
        ]);

        $visit = $this->visitInThisService();

        $action->execute($visit, $this->pathologyId);

        session()->flash('service.status', sprintf(
            'Pathologie notee pour %s.',
            $visit->patient->name,
        ));

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

    /**
     * L'ordonnance part dans le dossier medical (v3.3.1).
     *
     * L'ecran ne bouge pas, c'est toujours ici que le medecin ecrit, mais ce
     * qu'il enregistre atterrit desormais dans `dme_prescriptions`, table
     * unique des deux interfaces. Le PDF y gagne la mise en forme du dossier
     * medical sans rien perdre de la signature et des cachets.
     */
    public function savePrescription(CreateMedicalPrescription $action): void
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
            $prescription = $action->execute($visit, $this->currentAgent(), $this->prescriptionLines);
        } catch (InvalidArgumentException $e) {
            // Le medicament fait la ligne : le message le dit plutot que de
            // signaler un champ vide parmi vingt.
            $this->addError('prescriptionLines', $e->getMessage());

            return;
        }

        session()->flash('service.status', sprintf(
            'Ordonnance enregistree pour %s : %d ligne(s).',
            $visit->patient->name,
            $prescription->items()->count(),
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
            $action->execute($visit, $this->currentAgent(), Carbon::parse($this->appointmentAt), $this->serviceId);
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
            'pathologies' => Pathology::orderBy('name')->get(),
            'visits' => Visit::query()
                ->with('patient')
                ->inTodaysQueue($this->serviceId)
                ->where('status', Visit::STATUS_CALLED)
                ->orderBy('token')
                ->get(),
        ]);
    }
}
