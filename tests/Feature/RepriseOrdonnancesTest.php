<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientHistory;
use App\Models\Prescription as OrdonnanceHeritee;
use App\Models\Service;
use App\Services\PatientHistoryRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Keneya\Dme\Models\Prescription;
use Tests\TestCase;

/**
 * Reprise des ordonnances de WorkFlow dans le dossier medical (v3.3.1).
 *
 * Ce qui est verifie tient en trois promesses : rien ne se perd, rien ne se
 * duplique, et une ordonnance reprise se lit a sa place dans le dossier.
 */
class RepriseOrdonnancesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    public function test_une_ordonnance_de_workflow_atterrit_dans_le_dossier_medical(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);
        $patient = Patient::factory()->create(['name' => 'Moussa Keita']);
        $visit = $this->makeVisit($service, [], $patient);

        $ancienne = OrdonnanceHeritee::create([
            'patient_id' => $patient->getKey(),
            'visit_id' => $visit->getKey(),
            'doctor_id' => $doctor->getKey(),
            'lines' => [
                ['medicament' => 'Amoxicilline 1 g', 'posologie' => 'matin et soir', 'duree' => '7 jours'],
                ['medicament' => 'Paracetamol 500 mg', 'posologie' => null, 'duree' => null],
            ],
        ]);

        $this->artisan('keneya:reprise-ordonnances')->assertSuccessful();

        $reprise = Prescription::where('source_system', 'keneya_workflow')
            ->where('source_id', (string) $ancienne->getKey())
            ->firstOrFail();

        $this->assertSame($doctor->user_id, $reprise->doctor_id);
        $this->assertSame('validated', $reprise->status);
        $this->assertSame($patient->dossierMedical()->getKey(), $reprise->patient_id);

        $lignes = $reprise->items()->orderBy('position')->get();

        $this->assertCount(2, $lignes);
        $this->assertSame('Amoxicilline 1 g', $lignes[0]->medication_name);
        $this->assertSame('matin et soir', $lignes[0]->frequency);
        $this->assertSame('7 jours', $lignes[0]->duration);
        $this->assertSame('Paracetamol 500 mg', $lignes[1]->medication_name);
        $this->assertNull($lignes[1]->duration);
    }

    /**
     * Le nom du patient part entier dans le dossier medical : une ordonnance
     * ne doit pas ressortir au nom de « Keita Moussa ».
     */
    public function test_le_nom_du_patient_ne_se_reordonne_pas(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);
        $patient = Patient::factory()->create(['name' => 'Moussa Keita']);
        $visit = $this->makeVisit($service, [], $patient);

        OrdonnanceHeritee::create([
            'patient_id' => $patient->getKey(),
            'visit_id' => $visit->getKey(),
            'doctor_id' => $doctor->getKey(),
            'lines' => [['medicament' => 'Amoxicilline 1 g']],
        ]);

        $this->artisan('keneya:reprise-ordonnances')->assertSuccessful();

        $this->assertSame('Moussa Keita', $patient->dossierMedical()->fullName());
    }

    /**
     * La commande doit pouvoir etre relancee : on la lance une premiere fois
     * pour verifier le compte, puis de nouveau apres correction.
     */
    public function test_la_reprise_relancee_ne_duplique_rien(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);
        $patient = Patient::factory()->create();
        $visit = $this->makeVisit($service, [], $patient);

        OrdonnanceHeritee::create([
            'patient_id' => $patient->getKey(),
            'visit_id' => $visit->getKey(),
            'doctor_id' => $doctor->getKey(),
            'lines' => [['medicament' => 'Amoxicilline 1 g']],
        ]);

        $this->artisan('keneya:reprise-ordonnances')->assertSuccessful();
        $this->artisan('keneya:reprise-ordonnances')->assertSuccessful();

        $this->assertSame(1, Prescription::count());
    }

    public function test_la_simulation_n_ecrit_rien(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);
        $patient = Patient::factory()->create();
        $visit = $this->makeVisit($service, [], $patient);

        OrdonnanceHeritee::create([
            'patient_id' => $patient->getKey(),
            'visit_id' => $visit->getKey(),
            'doctor_id' => $doctor->getKey(),
            'lines' => [['medicament' => 'Amoxicilline 1 g']],
        ]);

        $this->artisan('keneya:reprise-ordonnances', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, Prescription::count());
    }

    /**
     * La frise du dossier retrouve l'ordonnance reprise : c'est ce qui
     * distingue une reprise d'une simple copie.
     */
    public function test_la_ligne_d_historique_designe_l_ordonnance_reprise(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);
        $patient = Patient::factory()->create();
        $visit = $this->makeVisit($service, [], $patient);

        $ancienne = OrdonnanceHeritee::create([
            'patient_id' => $patient->getKey(),
            'visit_id' => $visit->getKey(),
            'doctor_id' => $doctor->getKey(),
            'lines' => [['medicament' => 'Amoxicilline 1 g']],
        ]);

        app(PatientHistoryRecorder::class)->record(
            visit: $visit,
            type: PatientHistory::TYPE_PRESCRIPTION,
            description: 'Ordonnance etablie par '.$doctor->name().'.',
            doctor: $doctor,
        );

        $this->artisan('keneya:reprise-ordonnances')->assertSuccessful();

        $reprise = Prescription::where('source_id', (string) $ancienne->getKey())->firstOrFail();

        $this->assertDatabaseHas('patient_history', [
            'visit_id' => $visit->getKey(),
            'type' => PatientHistory::TYPE_PRESCRIPTION,
            'dme_prescription_id' => $reprise->getKey(),
        ]);
    }

    /**
     * Deux ordonnances du meme passage doivent retrouver chacune sa ligne
     * d'historique, dans l'ordre ou elles ont ete ecrites.
     */
    public function test_deux_ordonnances_du_meme_passage_ne_se_melangent_pas(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);
        $patient = Patient::factory()->create();
        $visit = $this->makeVisit($service, [], $patient);
        $recorder = app(PatientHistoryRecorder::class);

        $premiere = OrdonnanceHeritee::create([
            'patient_id' => $patient->getKey(),
            'visit_id' => $visit->getKey(),
            'doctor_id' => $doctor->getKey(),
            'lines' => [['medicament' => 'Amoxicilline 1 g']],
        ]);
        $recorder->record($visit, PatientHistory::TYPE_PRESCRIPTION, 'Premiere ordonnance.', doctor: $doctor);

        $seconde = OrdonnanceHeritee::create([
            'patient_id' => $patient->getKey(),
            'visit_id' => $visit->getKey(),
            'doctor_id' => $doctor->getKey(),
            'lines' => [['medicament' => 'Paracetamol 500 mg']],
        ]);
        $recorder->record($visit, PatientHistory::TYPE_PRESCRIPTION, 'Seconde ordonnance.', doctor: $doctor);

        $this->artisan('keneya:reprise-ordonnances')->assertSuccessful();

        $reprisePremiere = Prescription::where('source_id', (string) $premiere->getKey())->firstOrFail();
        $repriseSeconde = Prescription::where('source_id', (string) $seconde->getKey())->firstOrFail();

        $this->assertSame(
            $reprisePremiere->getKey(),
            PatientHistory::where('description', 'Premiere ordonnance.')->firstOrFail()->dme_prescription_id,
        );
        $this->assertSame(
            $repriseSeconde->getKey(),
            PatientHistory::where('description', 'Seconde ordonnance.')->firstOrFail()->dme_prescription_id,
        );
    }

    /**
     * Une ordonnance en texte libre, anterieure aux lignes structurees, se
     * reprend ligne par ligne : la reprise ne doit pas laisser sur le carreau
     * les dossiers les plus anciens.
     */
    public function test_une_ordonnance_en_texte_libre_se_reprend_ligne_par_ligne(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);
        $patient = Patient::factory()->create();
        $visit = $this->makeVisit($service, [], $patient);

        $ancienne = OrdonnanceHeritee::create([
            'patient_id' => $patient->getKey(),
            'visit_id' => $visit->getKey(),
            'doctor_id' => $doctor->getKey(),
            'content' => "Paracetamol 500 mg\nAmoxicilline 1 g\n\n",
        ]);

        $this->artisan('keneya:reprise-ordonnances')->assertSuccessful();

        $reprise = Prescription::where('source_id', (string) $ancienne->getKey())->firstOrFail();

        $this->assertSame(2, $reprise->items()->count());
    }
}
