<?php

declare(strict_types=1);

namespace Keneya\Dme\Tests\Feature;

use Keneya\Dme\Models\Patient;
use Keneya\Dme\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Keneya\Dme\Tests\TestCase;

/**
 * API REST (§43).
 *
 * Vérifie l'authentification par jeton, la forme des représentations
 * (alignée FHIR, §44) et surtout le fait que l'API applique exactement
 * les mêmes policies que l'interface web.
 */
class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    public function test_un_jeton_est_delivre_avec_des_identifiants_valides(): void
    {
        $user = $this->userWithRole(Rbac::ROLE_DOCTOR);

        $this->postJson(route('dme.api.auth.token'), [
            'email' => $user->email,
            'password' => 'MotDePasseDeTest2026',
            'device_name' => 'Tests automatisés',
        ])
            ->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'roles', 'permissions']]);
    }

    public function test_un_compte_desactive_n_obtient_aucun_jeton(): void
    {
        $user = $this->userWithRole(Rbac::ROLE_DOCTOR, ['is_active' => false]);

        $this->postJson(route('dme.api.auth.token'), [
            'email' => $user->email,
            'password' => 'MotDePasseDeTest2026',
            'device_name' => 'Tests automatisés',
        ])->assertStatus(422);
    }

    public function test_la_liste_des_patients_suit_la_structure_fhir(): void
    {
        Patient::factory()->count(3)->create();

        Sanctum::actingAs($this->userWithRole(Rbac::ROLE_DOCTOR));

        $this->getJson(route('dme.api.patients.index'))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'identifier', 'name' => ['family', 'given', 'text'], 'gender', 'birthDate']],
                'links',
                'meta',
            ]);
    }

    public function test_la_recherche_api_filtre_les_patients(): void
    {
        Patient::factory()->create(['last_name' => 'Traoré', 'first_name' => 'Mamadou']);
        Patient::factory()->create(['last_name' => 'Sow', 'first_name' => 'Awa']);

        Sanctum::actingAs($this->userWithRole(Rbac::ROLE_DOCTOR));

        $this->getJson(route('dme.api.patients.index', ['q' => 'Traoré']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name.family', 'Traoré');
    }

    public function test_un_patient_peut_etre_cree_par_l_api(): void
    {
        Sanctum::actingAs($this->userWithRole(Rbac::ROLE_DOCTOR));

        $this->postJson(route('dme.api.patients.store'), [
            'last_name' => 'Konaté',
            'first_name' => 'Seydou',
            'sex' => 'male',
            'birth_date' => '1975-06-12',
            'phone' => '+22370001020',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name.family', 'Konaté');

        $this->assertMatchesRegularExpression('/^PAT-\d{4}-000001$/', Patient::sole()->patient_number);
    }

    public function test_l_api_applique_les_memes_policies_que_l_interface(): void
    {
        $patient = Patient::factory()->create();

        // Le laboratoire peut lire un patient...
        Sanctum::actingAs($this->userWithRole(Rbac::ROLE_LAB));
        $this->getJson(route('dme.api.patients.show', $patient))->assertOk();

        // ...mais ne peut pas en créer.
        $this->postJson(route('dme.api.patients.store'), [
            'last_name' => 'Test', 'first_name' => 'Interdit', 'sex' => 'male',
        ])->assertForbidden();
    }

    public function test_l_api_refuse_les_ordonnances_a_un_role_non_prescripteur(): void
    {
        $patient = Patient::factory()->create();

        Sanctum::actingAs($this->userWithRole(Rbac::ROLE_PHARMACIST));

        $this->postJson(route('dme.api.patients.prescriptions.store', $patient), [
            'issued_on' => now()->toDateString(),
            'items' => [['medication_name' => 'Amoxicilline']],
        ])->assertForbidden();

        $this->assertDatabaseCount('dme_prescriptions', 0);
    }

    public function test_le_controle_d_allergie_s_applique_aussi_via_l_api(): void
    {
        $patient = Patient::factory()->create();

        \Keneya\Dme\Models\Allergy::create([
            'patient_id' => $patient->id,
            'allergen' => 'Pénicilline',
            'severity' => 'severe',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->userWithRole(Rbac::ROLE_DOCTOR));

        $this->postJson(route('dme.api.patients.prescriptions.store', $patient), [
            'issued_on' => now()->toDateString(),
            'items' => [['medication_name' => 'Amoxicilline', 'dosage' => '500 mg']],
        ])
            ->assertCreated()
            ->assertJsonPath('data.allergyWarnings.0.allergen', 'Pénicilline');
    }

    public function test_la_validation_api_refuse_une_ordonnance_sans_medicament(): void
    {
        $patient = Patient::factory()->create();

        Sanctum::actingAs($this->userWithRole(Rbac::ROLE_DOCTOR));

        $this->postJson(route('dme.api.patients.prescriptions.store', $patient), [
            'issued_on' => now()->toDateString(),
            'items' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('items');
    }

    public function test_le_chemin_de_stockage_n_apparait_pas_dans_l_api_documents(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $patient = Patient::factory()->create();
        $user = $this->userWithRole(Rbac::ROLE_DOCTOR);

        Sanctum::actingAs($user);

        $this->postJson(route('dme.api.documents.store'), [
            'patient_id' => $patient->id,
            'title' => 'Compte rendu',
            'type' => 'imported',
            'file' => \Illuminate\Http\UploadedFile::fake()->create('cr.pdf', 20, 'application/pdf'),
        ])->assertCreated();

        $response = $this->getJson(route('dme.api.patients.documents', $patient))->assertOk();

        $payload = $response->json('data.0');
        $this->assertArrayNotHasKey('storage_path', $payload);
        $this->assertArrayHasKey('downloadUrl', $payload);
        $this->assertStringContainsString('/documents/', $payload['downloadUrl']);
    }
}
