<?php

namespace App\Livewire\Service;

use App\Actions\Dme\OrderImaging;
use App\Actions\Dme\OrderLaboratory;
use App\Actions\Dme\RecordMedication;
use App\Actions\Dme\StoreMedicalDocument;
use App\Livewire\Concerns\RequiresCapability;
use App\Livewire\Service\Concerns\ScopedToOwnService;
use App\Models\StaffType;
use App\Models\Visit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Keneya\Dme\Models\ImagingOrder;
use Keneya\Dme\Models\MedicalDocument;
use Keneya\Dme\Models\Medication;
use Keneya\Dme\Models\Patient as DossierMedical;
use Keneya\Dme\Patients\PatientIdentifierResolver;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Traitements, examens et documents, saisis dans /service (v3.3.1).
 *
 * Quatre formulaires derriere quatre capacites, sur un ecran commun : ils
 * partagent le meme patient et le meme geste — completer le dossier medical
 * pendant que le patient est la. Chacun reste neanmoins independant, et un
 * type de personnel peut n'en recevoir qu'un seul.
 *
 * Ce que ces ecrans ne remplacent pas : le renvoi inter-service de WorkFlow,
 * qui fait circuler le patient vers le laboratoire ou l'echographie et porte
 * sa facturation. Les deux coexistent — le renvoi conduit le patient, la
 * demande documente l'acte au dossier.
 */
class MedicalOrders extends Component
{
    use RequiresCapability, ScopedToOwnService, WithFileUploads;

    public ?int $visitId = null;

    // ------------------------------------------------ Traitement habituel

    public string $medicationName = '';

    public string $dosage = '';

    public string $frequency = '';

    public string $route = '';

    public string $medicationComment = '';

    // ------------------------------------------------------- Laboratoire

    /**
     * Analyses demandees. Une ligne vide est ouverte d'emblee.
     *
     * @var array<int, array{name: string, category: string}>
     */
    public array $exams = [self::ANALYSE_VIDE];

    public string $labPriority = 'routine';

    public string $labIndication = '';

    /** Garde-fou : une demande ne porte pas quarante analyses. */
    public const MAX_ANALYSES = 15;

    private const ANALYSE_VIDE = ['name' => '', 'category' => ''];

    // ---------------------------------------------------------- Imagerie

    public string $modality = 'ultrasound';

    public string $bodySite = '';

    public string $imagingPriority = 'routine';

    public string $imagingIndication = '';

    // --------------------------------------------------------- Documents

    public string $documentType = 'imported';

    public string $documentTitle = '';

    public $document = null;

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
        $this->resetTraitement();
        $this->resetLaboratoire();
        $this->resetImagerie();
        $this->resetDocument();
    }

    private function resetTraitement(): void
    {
        $this->reset(['medicationName', 'dosage', 'frequency', 'route', 'medicationComment']);
        $this->resetValidation();
    }

    private function resetLaboratoire(): void
    {
        $this->reset(['labIndication']);
        $this->exams = [self::ANALYSE_VIDE];
        $this->labPriority = 'routine';
        $this->resetValidation();
    }

    private function resetImagerie(): void
    {
        $this->reset(['bodySite', 'imagingIndication']);
        $this->modality = 'ultrasound';
        $this->imagingPriority = 'routine';
        $this->resetValidation();
    }

    private function resetDocument(): void
    {
        $this->reset(['documentTitle', 'document']);
        $this->documentType = 'imported';
        $this->resetValidation();
    }

    // ------------------------------------------------ Traitement habituel

    public function saveMedication(RecordMedication $action): void
    {
        $this->assertCapability(StaffType::CAP_RECORD_MEDICATIONS);

        $this->validate([
            'visitId' => ['required', 'integer', 'exists:visits,id'],
            'medicationName' => ['required', 'string', 'min:2', 'max:150'],
            'dosage' => ['nullable', 'string', 'max:100'],
            'frequency' => ['nullable', 'string', 'max:100'],
            'route' => ['nullable', 'in:'.implode(',', array_keys(RecordMedication::ROUTES))],
            'medicationComment' => ['nullable', 'string', 'max:1000'],
        ], attributes: [
            'visitId' => 'patient',
            'medicationName' => 'traitement',
            'dosage' => 'dosage',
            'frequency' => 'frequence',
            'route' => 'voie',
        ]);

        $visit = $this->visitInThisService();

        $action->execute($visit, $this->currentDoctor(), [
            'name' => $this->medicationName,
            'dosage' => $this->dosage,
            'frequency' => $this->frequency,
            'route' => $this->route,
            'started_on' => null,
            'comment' => $this->medicationComment,
        ]);

        session()->flash('service.status', 'Traitement consigne au dossier medical.');
        $this->resetTraitement();
    }

    public function setMedicationStatus(int $medicationId, string $statut, RecordMedication $action): void
    {
        $this->assertCapability(StaffType::CAP_RECORD_MEDICATIONS);

        abort_unless(in_array($statut, ['suspended', 'stopped'], true), 422);

        $action->updateStatus(
            $this->visitInThisService(),
            $this->currentDoctor(),
            Medication::findOrFail($medicationId),
            $statut,
        );

        session()->flash('service.status', 'Traitement mis a jour au dossier medical.');
    }

    // ------------------------------------------------------- Laboratoire

    public function addExam(): void
    {
        if (count($this->exams) >= self::MAX_ANALYSES) {
            return;
        }

        $this->exams[] = self::ANALYSE_VIDE;
    }

    public function removeExam(int $index): void
    {
        if (! array_key_exists($index, $this->exams)) {
            return;
        }

        if (count($this->exams) === 1) {
            $this->exams = [self::ANALYSE_VIDE];

            return;
        }

        unset($this->exams[$index]);
        $this->exams = array_values($this->exams);
    }

    public function saveLabOrder(OrderLaboratory $action): void
    {
        $this->assertCapability(StaffType::CAP_ORDER_LABORATORY);

        $this->validate([
            'visitId' => ['required', 'integer', 'exists:visits,id'],
            'labPriority' => ['required', 'in:'.implode(',', array_keys(OrderLaboratory::PRIORITIES))],
            'labIndication' => ['nullable', 'string', 'max:1000'],
            'exams.*.name' => ['nullable', 'string', 'max:150'],
            'exams.*.category' => ['nullable', 'string', 'max:100'],
        ], attributes: [
            'visitId' => 'patient',
            'labPriority' => 'priorite',
            'labIndication' => 'indication',
        ]);

        // Une demande sans aucune analyse ne veut rien dire : le controle est
        // ici plutot que dans les regles, car il porte sur l'ensemble des
        // lignes et non sur l'une d'elles.
        $analyses = array_values(array_filter($this->exams, fn ($ligne) => filled($ligne['name'] ?? null)));

        if ($analyses === []) {
            $this->addError('exams', 'Indiquez au moins une analyse.');

            return;
        }

        $visit = $this->visitInThisService();

        $action->execute($visit, $this->currentDoctor(), [
            'requested_at' => now()->toDateTimeString(),
            'priority' => $this->labPriority,
            'indication' => $this->labIndication,
            'exams' => $analyses,
        ]);

        session()->flash('service.status', 'Demande d\'analyses posee au dossier medical.');
        $this->resetLaboratoire();
    }

    // ---------------------------------------------------------- Imagerie

    public function saveImagingOrder(OrderImaging $action): void
    {
        $this->assertCapability(StaffType::CAP_ORDER_IMAGING);

        $this->validate([
            'visitId' => ['required', 'integer', 'exists:visits,id'],
            // Exigee : elle conditionne le service qui realise l'examen, sa
            // duree et le compte rendu attendu.
            'modality' => ['required', 'in:'.implode(',', array_keys(ImagingOrder::MODALITIES))],
            'bodySite' => ['nullable', 'string', 'max:150'],
            'imagingPriority' => ['required', 'in:'.implode(',', array_keys(OrderLaboratory::PRIORITIES))],
            'imagingIndication' => ['nullable', 'string', 'max:1000'],
        ], attributes: [
            'visitId' => 'patient',
            'modality' => 'modalite',
            'bodySite' => 'region examinee',
            'imagingPriority' => 'priorite',
            'imagingIndication' => 'indication',
        ]);

        $visit = $this->visitInThisService();

        $action->execute($visit, $this->currentDoctor(), [
            'modality' => $this->modality,
            'body_site' => $this->bodySite,
            'requested_at' => now()->toDateTimeString(),
            'priority' => $this->imagingPriority,
            'indication' => $this->imagingIndication,
        ]);

        session()->flash('service.status', 'Demande d\'imagerie posee au dossier medical.');
        $this->resetImagerie();
    }

    // --------------------------------------------------------- Documents

    public function saveDocument(StoreMedicalDocument $action): void
    {
        $this->assertCapability(StaffType::CAP_RECORD_DOCUMENTS);

        $this->validate([
            'visitId' => ['required', 'integer', 'exists:visits,id'],
            'documentType' => ['required', 'in:'.implode(',', array_keys(MedicalDocument::TYPES))],
            'documentTitle' => ['nullable', 'string', 'max:200'],
            // Les bornes sont celles du module, pas des valeurs inventees ici.
            'document' => [
                'required', 'file',
                'max:'.$action->maxSizeKb(),
                'mimes:'.implode(',', $action->allowedExtensions()),
            ],
        ], attributes: [
            'visitId' => 'patient',
            'documentType' => 'type de document',
            'documentTitle' => 'titre',
            'document' => 'fichier',
        ]);

        $visit = $this->visitInThisService();

        $action->execute(
            $visit,
            $this->currentDoctor(),
            $this->document,
            $this->documentType,
            $this->documentTitle ?: null,
        );

        session()->flash('service.status', 'Document verse au dossier medical.');
        $this->resetDocument();
    }

    // ------------------------------------------------------------ Rendu

    private function visitInThisService(): Visit
    {
        return Visit::with(['patient', 'service'])
            ->where('service_id', $this->serviceId)
            ->findOrFail($this->visitId);
    }

    /**
     * Le dossier medical du patient choisi, s'il en a deja un. Rien n'est
     * cree ici : consulter n'est pas ecrire.
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
        $compte = auth()->user();

        return view('livewire.service.medical-orders', [
            'visits' => Visit::query()
                ->with('patient')
                ->inTodaysQueue($this->serviceId)
                ->where('status', Visit::STATUS_CALLED)
                ->orderBy('token')
                ->get(),
            'medicationStatuses' => RecordMedication::STATUSES,
            'medicationRoutes' => RecordMedication::ROUTES,
            'priorities' => OrderLaboratory::PRIORITIES,
            'modalities' => ImagingOrder::MODALITIES,
            'documentTypes' => MedicalDocument::TYPES,
            'medications' => $dossier?->medications()->orderByDesc('id')->get() ?? new Collection,
            'labOrders' => $dossier?->labOrders()->with('items')->orderByDesc('id')->limit(5)->get() ?? new Collection,
            'imagingOrders' => $dossier?->imagingOrders()->orderByDesc('id')->limit(5)->get() ?? new Collection,
            'documents' => $dossier?->documents()->orderByDesc('id')->limit(10)->get() ?? new Collection,
            'peutTraitements' => $compte->hasCapability(StaffType::CAP_RECORD_MEDICATIONS),
            'peutLaboratoire' => $compte->hasCapability(StaffType::CAP_ORDER_LABORATORY),
            'peutImagerie' => $compte->hasCapability(StaffType::CAP_ORDER_IMAGING),
            'peutDocuments' => $compte->hasCapability(StaffType::CAP_RECORD_DOCUMENTS),
        ]);
    }
}
