<?php

namespace App\Livewire\Service;

use App\Actions\Dme\RecordMedicalConsultation;
use App\Livewire\Concerns\RequiresCapability;
use App\Livewire\Service\Concerns\ScopedToOwnService;
use App\Models\StaffType;
use App\Models\Visit;
use Illuminate\Contracts\View\View;
use Keneya\Dme\Models\ClinicalNote;
use Keneya\Dme\Models\Consultation;
use Keneya\Dme\Models\Diagnosis;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Consultation medicale, redigee dans /service (v3.3.1).
 *
 * Le formulaire porte l'apparence de WorkFlow et la structure du dossier
 * medical : motif, histoire de la maladie, constantes, examen par appareil,
 * diagnostics, conduite a tenir. Ce qui est saisi ici atterrit dans le DME —
 * `dme_consultations` et ses tables filles — et nulle part ailleurs.
 *
 * Le medecin ne quitte donc jamais son interface pour tenir le dossier
 * medical, et la donnee de sante ne transite pas par WorkFlow. C'etait le
 * point de depart du v3.3.1 : deplacer le lieu de saisie plutot que recopier
 * une donnee pauvre dans une structure riche.
 *
 * Meme selecteur de patient que « Fin de consultation » — les patients appeles
 * de la file du jour — pour que les deux onglets parlent du meme patient au
 * meme moment, sans que le medecin ait a se demander lequel des deux fait foi.
 */
class MedicalConsultation extends Component
{
    use RequiresCapability, ScopedToOwnService;

    public ?int $visitId = null;

    /** Date et heure de la consultation, prerenseignees a maintenant. */
    public string $startedAt = '';

    public string $type = 'ambulatory';

    public string $reason = '';

    public string $historyOfIllness = '';

    /**
     * Constantes. Toutes facultatives : un medecin qui n'en prend aucune ne
     * doit pas etre empeche d'ecrire sa consultation.
     *
     * @var array<string, string>
     */
    public array $vitals = [
        'temperature' => '', 'systolic' => '', 'diastolic' => '', 'heart_rate' => '',
        'respiratory_rate' => '', 'oxygen_saturation' => '', 'weight' => '',
        'height' => '', 'glycemia' => '',
    ];

    /**
     * Examen clinique, un champ par appareil. Les appareils viennent du
     * module : c'est lui qui tient la liste, pas WorkFlow.
     *
     * @var array<string, string>
     */
    public array $exam = [];

    /**
     * Diagnostics poses. Une ligne vide est ouverte d'emblee : le medecin
     * n'a pas a cliquer pour commencer a ecrire.
     *
     * @var array<int, array{label: string, code: string, type: string, status: string, comment: string}>
     */
    public array $diagnoses = [self::DIAGNOSTIC_VIDE];

    public string $treatmentPlan = '';

    public string $followUp = '';

    public string $recommendations = '';

    /** Garde-fou : une consultation ne pose pas quinze diagnostics. */
    public const MAX_DIAGNOSTICS = 8;

    private const DIAGNOSTIC_VIDE = [
        'label' => '', 'code' => '', 'type' => 'primary', 'status' => 'suspected', 'comment' => '',
    ];

    public function mount(int $serviceId): void
    {
        $this->serviceId = $this->assertOwnService($serviceId);
        $this->resetServiceState();
    }

    #[On('service-change')]
    public function handleServiceChange(int $serviceId): void
    {
        $this->onServiceChanged($serviceId);
    }

    #[On('file-mise-a-jour')]
    public function refreshPanel(): void
    {
        // Un nouveau rendu suffit : la liste des patients appeles a change.
    }

    protected function resetServiceState(): void
    {
        $this->reset([
            'visitId', 'type', 'reason', 'historyOfIllness',
            'treatmentPlan', 'followUp', 'recommendations',
        ]);

        $this->startedAt = now()->format('Y-m-d\TH:i');
        $this->vitals = array_fill_keys(array_keys($this->vitals), '');
        $this->exam = array_fill_keys(array_keys(ClinicalNote::SYSTEMS), '');
        $this->diagnoses = [self::DIAGNOSTIC_VIDE];
        $this->resetValidation();
    }

    public function addDiagnosis(): void
    {
        if (count($this->diagnoses) >= self::MAX_DIAGNOSTICS) {
            return;
        }

        $this->diagnoses[] = self::DIAGNOSTIC_VIDE;
    }

    /**
     * La derniere ligne se vide au lieu de disparaitre : un formulaire sans
     * aucune ligne oblige a cliquer pour recommencer a ecrire.
     */
    public function removeDiagnosis(int $index): void
    {
        if (! array_key_exists($index, $this->diagnoses)) {
            return;
        }

        if (count($this->diagnoses) === 1) {
            $this->diagnoses = [self::DIAGNOSTIC_VIDE];

            return;
        }

        unset($this->diagnoses[$index]);
        $this->diagnoses = array_values($this->diagnoses);
    }

    public function save(RecordMedicalConsultation $action): void
    {
        $this->assertCapability(StaffType::CAP_RECORD_CONSULTATION);

        $donnees = $this->validate([
            'visitId' => ['required', 'integer', 'exists:visits,id'],
            'startedAt' => ['required', 'date', 'before_or_equal:now'],
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(Consultation::TYPES))],

            // Le motif est le seul champ clinique exige : une consultation
            // sans motif ne dit rien de ce qui a amene le patient.
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'historyOfIllness' => ['nullable', 'string', 'max:10000'],

            // Les bornes sont celles du module, pas des valeurs inventees ici.
            'vitals.temperature' => ['nullable', 'numeric', 'between:25,45'],
            'vitals.systolic' => ['nullable', 'integer', 'between:40,300'],
            'vitals.diastolic' => ['nullable', 'integer', 'between:20,200'],
            'vitals.heart_rate' => ['nullable', 'integer', 'between:20,250'],
            'vitals.respiratory_rate' => ['nullable', 'integer', 'between:5,80'],
            'vitals.oxygen_saturation' => ['nullable', 'integer', 'between:50,100'],
            'vitals.weight' => ['nullable', 'numeric', 'between:0.5,400'],
            'vitals.height' => ['nullable', 'numeric', 'between:20,250'],
            'vitals.glycemia' => ['nullable', 'numeric', 'between:0.1,10'],

            'exam.*' => ['nullable', 'string', 'max:5000'],

            'diagnoses.*.label' => ['nullable', 'string', 'max:200'],
            'diagnoses.*.code' => ['nullable', 'string', 'max:20'],
            'diagnoses.*.type' => ['nullable', 'in:primary,secondary,differential'],
            'diagnoses.*.status' => ['nullable', 'in:'.implode(',', array_keys(Diagnosis::STATUSES))],
            'diagnoses.*.comment' => ['nullable', 'string', 'max:1000'],

            'treatmentPlan' => ['nullable', 'string', 'max:10000'],
            'followUp' => ['nullable', 'string', 'max:5000'],
            'recommendations' => ['nullable', 'string', 'max:5000'],
        ], attributes: [
            'visitId' => 'patient',
            'startedAt' => 'date de consultation',
            'type' => 'type de consultation',
            'reason' => 'motif',
            'historyOfIllness' => 'histoire de la maladie',
            'treatmentPlan' => 'conduite a tenir',
            'followUp' => 'suivi',
            'recommendations' => 'recommandations',
        ]);

        // Le service est verifie ici, et pas seulement a l'affichage : un
        // identifiant de visite force cote client ne doit pas ouvrir le
        // dossier d'un patient d'un autre service.
        $visit = $this->visitInThisService();

        $consultation = $action->execute($visit, $this->currentDoctor(), [
            'started_at' => $donnees['startedAt'],
            'type' => $donnees['type'],
            'reason' => $donnees['reason'],
            'history_of_illness' => $this->historyOfIllness ?: null,
            'treatment_plan' => $this->treatmentPlan ?: null,
            'follow_up' => $this->followUp ?: null,
            'recommendations' => $this->recommendations ?: null,
            'vitals' => $this->vitals,
            'exam' => $this->exam,
            'diagnoses' => $this->diagnoses,
        ]);

        session()->flash('service.status', sprintf(
            'Consultation %s enregistree au dossier medical de %s.',
            $consultation->consultation_number,
            $visit->patient->name,
        ));

        $this->resetServiceState();
        $this->dispatch('file-mise-a-jour');
    }

    /**
     * La visite choisie, si elle appartient bien au service courant.
     */
    private function visitInThisService(): Visit
    {
        return Visit::with(['patient', 'service'])
            ->where('service_id', $this->serviceId)
            ->findOrFail($this->visitId);
    }

    public function render(): View
    {
        return view('livewire.service.medical-consultation', [
            'visits' => Visit::query()
                ->with('patient')
                ->inTodaysQueue($this->serviceId)
                ->where('status', Visit::STATUS_CALLED)
                ->orderBy('token')
                ->get(),
            'types' => Consultation::TYPES,
            'systems' => ClinicalNote::SYSTEMS,
            'diagnosisStatuses' => Diagnosis::STATUSES,
        ]);
    }
}
