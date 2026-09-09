<?php

namespace App\Livewire\Service;

use App\Actions\Dme\RecordAllergy;
use App\Actions\Dme\RecordMedicalHistory;
use App\Livewire\Concerns\RequiresCapability;
use App\Livewire\Service\Concerns\ScopedToOwnService;
use App\Models\StaffType;
use App\Models\Visit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Keneya\Dme\Models\Allergy;
use Keneya\Dme\Models\MedicalHistory;
use Keneya\Dme\Models\Patient as DossierMedical;
use Keneya\Dme\Patients\PatientIdentifierResolver;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Antecedents et allergies, saisis dans /service (v3.3.1).
 *
 * Un seul composant pour les deux, mais **deux capacites distinctes** : le
 * meme ecran peut donc n'en montrer qu'une moitie. Un hopital qui laisse ses
 * infirmieres relever les allergies sans leur confier les antecedents obtient
 * exactement cela, sans qu'aucune regle ne soit ecrite deux fois.
 *
 * Ce sont les seules donnees du dossier qui appartiennent au patient plutot
 * qu'a un passage : un antecedent chirurgical vaut pour toute la vie du
 * dossier. Le patient se choisit malgre tout dans la file du jour, comme
 * partout ailleurs dans /service : on ne consigne pas un antecedent pour
 * quelqu'un qui n'est pas devant soi.
 */
class MedicalBackground extends Component
{
    use RequiresCapability, ScopedToOwnService;

    public ?int $visitId = null;

    // ------------------------------------------------------- Antecedent

    public string $category = 'personal';

    public string $label = '';

    public string $year = '';

    /** Lien de parente : antecedents familiaux seulement. */
    public string $relative = '';

    /** Etablissement : antecedents chirurgicaux seulement. */
    public string $facility = '';

    public string $comment = '';

    // ---------------------------------------------------------- Allergie

    public string $allergen = '';

    public string $allergenType = '';

    public string $reaction = '';

    public string $severity = 'unknown';

    public string $allergyComment = '';

    /** @var array<string, string> */
    public const ALLERGEN_TYPES = [
        'medication' => 'Medicament',
        'food' => 'Aliment',
        'environment' => 'Environnement',
        'other' => 'Autre',
    ];

    public function mount(int $serviceId): void
    {
        $this->serviceId = $this->assertOwnService($serviceId);
    }

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
        $this->reset(['visitId']);
        $this->resetAntecedent();
        $this->resetAllergie();
    }

    private function resetAntecedent(): void
    {
        $this->reset(['label', 'year', 'relative', 'facility', 'comment']);
        $this->category = 'personal';
        $this->resetValidation();
    }

    private function resetAllergie(): void
    {
        $this->reset(['allergen', 'allergenType', 'reaction', 'allergyComment']);
        $this->severity = 'unknown';
        $this->resetValidation();
    }

    // ------------------------------------------------------------ Actions

    public function saveHistory(RecordMedicalHistory $action): void
    {
        $this->assertCapability(StaffType::CAP_RECORD_HISTORY);

        $this->validate([
            'visitId' => ['required', 'integer', 'exists:visits,id'],
            'category' => ['required', 'in:'.implode(',', array_keys(MedicalHistory::CATEGORIES))],
            'label' => ['required', 'string', 'min:2', 'max:200'],
            // Une annee approximative vaut mieux qu'une date inventee : le
            // patient se souvient rarement du jour de son appendicectomie.
            'year' => ['nullable', 'digits:4', 'integer', 'min:1900', 'max:'.now()->year],
            'relative' => ['nullable', 'string', 'max:100'],
            'facility' => ['nullable', 'string', 'max:150'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ], attributes: [
            'visitId' => 'patient',
            'category' => 'categorie',
            'label' => 'antecedent',
            'year' => 'annee',
            'relative' => 'lien de parente',
            'facility' => 'etablissement',
        ]);

        $visit = $this->visitInThisService();

        $action->execute($visit, $this->currentAgent(), [
            'category' => $this->category,
            'label' => $this->label,
            'year' => $this->year,
            'relative' => $this->relative,
            'facility' => $this->facility,
            'comment' => $this->comment,
        ]);

        session()->flash('service.status', sprintf(
            'Antecedent consigne au dossier medical de %s.',
            $visit->patient->name,
        ));

        $this->resetAntecedent();
    }

    public function saveAllergy(RecordAllergy $action): void
    {
        $this->assertCapability(StaffType::CAP_RECORD_ALLERGIES);

        $this->validate([
            'visitId' => ['required', 'integer', 'exists:visits,id'],
            'allergen' => ['required', 'string', 'min:2', 'max:150'],
            'allergenType' => ['nullable', 'in:'.implode(',', array_keys(self::ALLERGEN_TYPES))],
            'reaction' => ['nullable', 'string', 'max:200'],
            // Exigee, et pas laissee a « inconnue » par defaut : c'est elle
            // que le module lit pour alerter au moment de prescrire.
            'severity' => ['required', 'in:'.implode(',', array_keys(Allergy::SEVERITIES))],
            'allergyComment' => ['nullable', 'string', 'max:1000'],
        ], attributes: [
            'visitId' => 'patient',
            'allergen' => 'allergene',
            'allergenType' => 'type',
            'reaction' => 'reaction',
            'severity' => 'severite',
            'allergyComment' => 'commentaire',
        ]);

        $visit = $this->visitInThisService();

        $action->execute($visit, $this->currentAgent(), [
            'allergen' => $this->allergen,
            'allergen_type' => $this->allergenType,
            'reaction' => $this->reaction,
            'severity' => $this->severity,
            'comment' => $this->allergyComment,
        ]);

        session()->flash('service.status', sprintf(
            'Allergie consignee au dossier medical de %s.',
            $visit->patient->name,
        ));

        $this->resetAllergie();
    }

    /**
     * Marque une allergie resolue ou refutee. Jamais de suppression : une
     * allergie evoquee puis ecartee est une information clinique.
     */
    public function setAllergyStatus(int $allergyId, string $statut, RecordAllergy $action): void
    {
        $this->assertCapability(StaffType::CAP_RECORD_ALLERGIES);

        abort_unless(in_array($statut, ['resolved', 'refuted'], true), 422);

        $visit = $this->visitInThisService();

        $action->updateStatus($visit, $this->currentAgent(), Allergy::findOrFail($allergyId), $statut);

        session()->flash('service.status', 'Allergie mise a jour au dossier medical.');
    }

    private function visitInThisService(): Visit
    {
        return Visit::with(['patient', 'service'])
            ->where('service_id', $this->serviceId)
            ->findOrFail($this->visitId);
    }

    /**
     * Le dossier medical du patient choisi, s'il en a deja un.
     *
     * Rien n'est cree ici : un ecran qui se contente d'afficher ne doit pas
     * fabriquer un dossier au seul motif qu'on l'a ouvert. La creation vient
     * a la premiere saisie, dans l'action.
     */
    private function dossier(): ?DossierMedical
    {
        if (! $this->visitId) {
            return null;
        }

        $code = Visit::where('service_id', $this->serviceId)
            ->whereKey($this->visitId)
            ->with('patient:id,patient_code')
            ->first()?->patient?->patient_code;

        return $code === null
            ? null
            : app(PatientIdentifierResolver::class)->find('keneya_workflow', $code);
    }

    public function render(): View
    {
        $dossier = $this->dossier();

        return view('livewire.service.medical-background', [
            'visits' => Visit::query()
                ->with('patient')
                ->inTodaysQueue($this->serviceId)
                ->where('status', Visit::STATUS_CALLED)
                ->orderBy('token')
                ->get(),
            'categories' => MedicalHistory::CATEGORIES,
            'severities' => Allergy::SEVERITIES,
            'allergenTypes' => self::ALLERGEN_TYPES,
            'histories' => $dossier?->medicalHistories()->orderByDesc('id')->get() ?? new Collection,
            'allergies' => $dossier?->allergies()->orderByDesc('id')->get() ?? new Collection,
            'peutAntecedents' => auth()->user()->hasCapability(StaffType::CAP_RECORD_HISTORY),
            'peutAllergies' => auth()->user()->hasCapability(StaffType::CAP_RECORD_ALLERGIES),
        ]);
    }
}
