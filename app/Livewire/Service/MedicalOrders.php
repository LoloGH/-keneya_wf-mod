<?php

namespace App\Livewire\Service;

use App\Actions\Dme\RecordMedication;
use App\Actions\Dme\StoreMedicalDocument;
use App\Livewire\Concerns\RequestsExamination;
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
 * Traitements, examens et documents du dossier medical, dans /service (v3.3.1).
 *
 * Deux formulaires derriere deux capacites, traitements habituels et
 * documents, sur un ecran commun : ils partagent le meme patient et le meme
 * geste, completer le dossier pendant que le patient est la. Chacun reste
 * independant, et un type de personnel peut n'en recevoir qu'un seul.
 *
 * Les demandes d'examens, elles, ne se **posent** plus ici : elles s'y lisent
 * seulement. Demander un examen, c'est envoyer le patient le faire : le
 * formulaire vit donc dans le renvoi, ou il apparait des que la destination
 * realise des examens ({@see RequestsExamination}).
 * Les avoir separes laissait le technicien recevoir un patient sans savoir ce
 * qu'on lui demandait, et la demande dormir au dossier sans destinataire.
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
        $this->resetDocument();
    }

    private function resetTraitement(): void
    {
        $this->reset(['medicationName', 'dosage', 'frequency', 'route', 'medicationComment']);
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

        $action->execute($visit, $this->currentAgent(), [
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
            $this->currentAgent(),
            Medication::findOrFail($medicationId),
            $statut,
        );

        session()->flash('service.status', 'Traitement mis a jour au dossier medical.');
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
            $this->currentAgent(),
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
