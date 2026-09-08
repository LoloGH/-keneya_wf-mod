<?php

namespace Tests\Feature;

use App\Livewire\Service\MedicalOrders;
use App\Models\Patient;
use App\Models\Service;
use App\Models\StaffType;
use App\Models\User;
use App\Models\Visit;
use App\Support\Roles;
use Database\Seeders\DmePermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Keneya\Dme\Models\ImagingOrder;
use Keneya\Dme\Models\LabOrder;
use Keneya\Dme\Models\MedicalDocument;
use Keneya\Dme\Models\Medication;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Traitements, examens et documents, saisis depuis /service (v3.3.1).
 *
 * Quatre formulaires, quatre capacités, un écran. Le fil conducteur de ces
 * tests : chacune se confie séparément, et tout atterrit dans le DME.
 */
class MedicalOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->seed(DmePermissionSeeder::class);
    }

    /**
     * @param  array<int, string>  $capacites
     * @return array{0: User, 1: Visit, 2: Service}
     */
    private function medecinEtPatientAppele(array $capacites): array
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $doctor = $this->makeDoctor($service);

        $this->staffTypeFor(Roles::DOCTOR)->update(['capabilities' => $capacites]);

        $patient = Patient::factory()->create(['name' => 'Aminata Traore', 'gender' => 'Femme', 'age' => 34]);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED], $patient);

        return [$doctor->user, $visit, $service];
    }

    private function ecran(User $medecin, Service $service, Visit $visit): Testable
    {
        return Livewire::actingAs($medecin)
            ->test(MedicalOrders::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey());
    }

    // ------------------------------------------------------- Les capacités

    /**
     * Chaque bloc se confie separement, et le refus tient cote serveur.
     *
     * @return array<string, array{0: string,1: string, 2: string}>
     */
    public static function blocsEtCapacites(): array
    {
        return [
            'traitements' => [StaffType::CAP_RECORD_MEDICATIONS, 'saveMedication', 'dme_medications'],
            'laboratoire' => [StaffType::CAP_ORDER_LABORATORY, 'saveLabOrder', 'dme_lab_orders'],
            'imagerie' => [StaffType::CAP_ORDER_IMAGING, 'saveImagingOrder', 'dme_imaging_orders'],
            'documents' => [StaffType::CAP_RECORD_DOCUMENTS, 'saveDocument', 'dme_medical_documents'],
        ];
    }

    #[DataProvider('blocsEtCapacites')]
    public function test_chaque_bloc_refuse_sans_sa_capacite(string $capacite, string $methode, string $table): void
    {
        // Le compte porte les trois autres capacites, jamais celle-ci : le
        // refus vient bien du bloc, et non d'un manque general de droits.
        $autres = array_values(array_diff(array_column(self::blocsEtCapacites(), 0), [$capacite]));

        [$medecin, $visit, $service] = $this->medecinEtPatientAppele($autres);

        $this->ecran($medecin, $service, $visit)->call($methode)->assertForbidden();

        $this->assertDatabaseCount($table, 0);
    }

    public function test_ecrire_au_dossier_n_exige_pas_le_droit_de_l_ouvrir(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele([StaffType::CAP_ORDER_IMAGING]);

        $this->assertFalse($medecin->canAccessDme());

        $this->ecran($medecin, $service, $visit)
            ->set('modality', 'ultrasound')
            ->set('bodySite', 'Abdomen')
            ->call('saveImagingOrder')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('dme_imaging_orders', 1);
        $this->actingAs($medecin)->get(route('dme.home'))->assertForbidden();
    }

    // ------------------------------------------------ Traitements habituels

    public function test_un_traitement_atterrit_dans_le_dossier_medical(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele([StaffType::CAP_RECORD_MEDICATIONS]);

        $this->ecran($medecin, $service, $visit)
            ->set('medicationName', 'Amlodipine')
            ->set('dosage', '5 mg')
            ->set('frequency', '1 fois par jour')
            ->set('route', 'orale')
            ->call('saveMedication')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('dme_medications', [
            'name' => 'Amlodipine',
            'dosage' => '5 mg',
            'status' => 'active',
            'prescriber_id' => $medecin->getKey(),
        ]);
    }

    public function test_un_traitement_s_arrete_mais_ne_se_supprime_pas(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele([StaffType::CAP_RECORD_MEDICATIONS]);

        $ecran = $this->ecran($medecin, $service, $visit)
            ->set('medicationName', 'Amlodipine')
            ->call('saveMedication');

        $traitement = Medication::firstOrFail();
        $ecran->call('setMedicationStatus', $traitement->getKey(), 'stopped');

        // La ligne demeure : un traitement interrompu explique souvent la
        // venue suivante.
        $this->assertDatabaseCount('dme_medications', 1);
        $this->assertSame('stopped', $traitement->fresh()->status);
        $this->assertNotNull($traitement->fresh()->ended_on);
    }

    // ---------------------------------------------------------- Laboratoire

    public function test_une_demande_d_analyses_porte_ses_lignes(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele([StaffType::CAP_ORDER_LABORATORY]);

        $this->ecran($medecin, $service, $visit)
            ->set('exams.0.name', 'Numeration formule sanguine')
            ->set('exams.0.category', 'Hematologie')
            ->call('addExam')
            ->set('exams.1.name', 'Goutte epaisse')
            ->call('addExam')
            ->set('labPriority', 'urgent')
            ->set('labIndication', 'Fievre depuis trois jours.')
            ->call('saveLabOrder')
            ->assertHasNoErrors();

        $demande = LabOrder::firstOrFail();

        $this->assertSame('urgent', $demande->priority);
        $this->assertSame($medecin->getKey(), $demande->doctor_id);
        // Trois lignes ouvertes, deux remplies : la vide est ecartee.
        $this->assertSame(2, $demande->items()->count());
        $this->assertDatabaseHas('dme_lab_order_items', ['exam_name' => 'Goutte epaisse']);
    }

    public function test_une_demande_sans_aucune_analyse_est_refusee(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele([StaffType::CAP_ORDER_LABORATORY]);

        $this->ecran($medecin, $service, $visit)
            ->set('labIndication', 'Bilan.')
            ->call('saveLabOrder')
            ->assertHasErrors('exams');

        $this->assertDatabaseCount('dme_lab_orders', 0);
    }

    // ------------------------------------------------------------- Imagerie

    public function test_une_demande_d_imagerie_porte_sa_modalite(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele([StaffType::CAP_ORDER_IMAGING]);

        $this->ecran($medecin, $service, $visit)
            ->set('modality', 'ultrasound')
            ->set('bodySite', 'Abdomen')
            ->set('imagingPriority', 'urgent')
            ->call('saveImagingOrder')
            ->assertHasNoErrors();

        $demande = ImagingOrder::firstOrFail();

        $this->assertSame('ultrasound', $demande->modality);
        $this->assertSame('Abdomen', $demande->body_site);
        $this->assertSame('requested', $demande->status);
    }

    public function test_une_modalite_inconnue_est_refusee(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele([StaffType::CAP_ORDER_IMAGING]);

        $this->ecran($medecin, $service, $visit)
            ->set('modality', 'teleportation')
            ->call('saveImagingOrder')
            ->assertHasErrors('modality');

        $this->assertDatabaseCount('dme_imaging_orders', 0);
    }

    // ------------------------------------------------------------ Documents

    public function test_un_document_est_verse_au_dossier_sur_le_disque_partage(): void
    {
        Storage::fake('attachments');

        [$medecin, $visit, $service] = $this->medecinEtPatientAppele([StaffType::CAP_RECORD_DOCUMENTS]);

        $this->ecran($medecin, $service, $visit)
            ->set('documentType', 'imaging_report')
            ->set('documentTitle', 'Compte rendu d\'echographie')
            ->set('document', UploadedFile::fake()->create('echo.pdf', 120, 'application/pdf'))
            ->call('saveDocument')
            ->assertHasNoErrors();

        $document = MedicalDocument::firstOrFail();

        $this->assertSame('imaging_report', $document->type);
        $this->assertSame('Compte rendu d\'echographie', $document->title);
        $this->assertSame($medecin->getKey(), $document->uploaded_by);

        // Un seul disque pour les deux applications : c'est la decision du
        // chantier, et c'est le disque des pieces jointes de WorkFlow.
        $this->assertSame('attachments', $document->disk);
        Storage::disk('attachments')->assertExists($document->storage_path);
    }

    public function test_un_fichier_d_un_type_refuse_ne_passe_pas(): void
    {
        Storage::fake('attachments');

        [$medecin, $visit, $service] = $this->medecinEtPatientAppele([StaffType::CAP_RECORD_DOCUMENTS]);

        $this->ecran($medecin, $service, $visit)
            ->set('document', UploadedFile::fake()->create('script.exe', 10))
            ->call('saveDocument')
            ->assertHasErrors('document');

        $this->assertDatabaseCount('dme_medical_documents', 0);
    }

    // ------------------------------------------------------------ Le patient

    public function test_ouvrir_l_ecran_ne_cree_aucun_dossier_medical(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele([StaffType::CAP_ORDER_LABORATORY]);

        $this->ecran($medecin, $service, $visit)->assertOk();

        $this->assertDatabaseCount('dme_patients', 0);
    }

    public function test_un_patient_d_un_autre_service_reste_hors_de_portee(): void
    {
        [$medecin, , $service] = $this->medecinEtPatientAppele([StaffType::CAP_ORDER_IMAGING]);

        $autre = Service::factory()->create(['name' => 'Urgences']);
        $etrangere = $this->makeVisit($autre, ['status' => Visit::STATUS_CALLED]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($medecin)
            ->test(MedicalOrders::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $etrangere->getKey())
            ->set('modality', 'ultrasound')
            ->call('saveImagingOrder');
    }
}
