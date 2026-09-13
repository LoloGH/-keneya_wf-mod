<?php

namespace Tests\Feature;

use App\Actions\CorrectPatientIdentity;
use App\Livewire\Reception\PatientLookup;
use App\Livewire\Reception\PatientRegistrationForm;
use App\Models\Companion;
use App\Models\Patient;
use App\Models\Service;
use App\Services\SmsGateway;
use App\Services\SmsSendResult;
use App\Support\Dme\PatientProjection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Les deux formulaires patients demandent la meme chose (v3.3.2).
 *
 * L'accueil et le dossier medical decrivaient la meme personne de deux facons
 * differentes : un seul champ « nom complet » d'un cote, un nom et un prenom
 * de l'autre ; une carte d'identite relevee a l'accueil et invisible au
 * dossier ; un accompagnateur enregistre a l'accueil qui n'atteignait jamais
 * la « personne a prevenir ». Trois ecarts, trois fois la meme consequence :
 * une donnee saisie une fois qui devait etre resaisie ailleurs, ou qui se
 * perdait en chemin.
 */
class PatientIdentityAlignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->mock(SmsGateway::class)->shouldReceive('deliver')->andReturn(SmsSendResult::sent());
    }

    // --------------------------------------------- Nom et prenom separement

    public function test_l_accueil_saisit_un_nom_et_un_prenom(): void
    {
        $service = Service::factory()->create();

        Livewire::actingAs($this->makeReceptionist())
            ->test(PatientRegistrationForm::class)
            ->set('lastName', 'Drame')
            ->set('firstName', 'Fode')
            ->set('age', 33)
            ->set('gender', 'Homme')
            ->set('mobile', '76445566')
            ->set('service_id', $service->getKey())
            ->call('save')
            ->assertHasNoErrors();

        $patient = Patient::firstOrFail();

        $this->assertSame('Drame', $patient->last_name);
        $this->assertSame('Fode', $patient->first_name);

        // Le nom complet reste ce que lisent le ticket, le SMS et la
        // recherche : il est compose, jamais saisi.
        $this->assertSame('Fode Drame', $patient->name);
    }

    /**
     * Le prenom est facultatif : une personne connue sous un seul nom doit
     * pouvoir etre enregistree, comme un patient arrive sans papiers.
     */
    public function test_le_prenom_est_facultatif(): void
    {
        $service = Service::factory()->create();

        Livewire::actingAs($this->makeReceptionist())
            ->test(PatientRegistrationForm::class)
            ->set('lastName', 'Fanta')
            ->set('age', 60)
            ->set('gender', 'Femme')
            ->set('mobile', '76112233')
            ->set('service_id', $service->getKey())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Fanta', Patient::firstOrFail()->name);
    }

    /**
     * Un appelant qui ne tient qu'un nom complet - un import, une reprise, une
     * fabrique de test - n'obtient pas deux colonnes vides.
     */
    public function test_un_nom_complet_seul_est_decoupe(): void
    {
        $patient = Patient::factory()->create(['name' => 'Aminata Traore']);

        $this->assertSame('Aminata', $patient->first_name);
        $this->assertSame('Traore', $patient->last_name);
        $this->assertSame('Aminata Traore', $patient->name);
    }

    public function test_le_nom_et_le_prenom_partent_separement_au_dossier_medical(): void
    {
        $patient = Patient::factory()->create(['name' => 'Aminata Traore']);

        $dossier = PatientProjection::resolve($patient);

        $this->assertSame('Traore', $dossier->last_name);
        $this->assertSame('Aminata', $dossier->first_name);
        $this->assertSame('Aminata Traore', $dossier->fullName());
    }

    /**
     * Un dossier ancien, dont la migration a decoupe le nom au juge, se repare
     * a l'accueil : sinon la seule issue serait un second dossier.
     */
    public function test_l_accueil_corrige_le_nom_et_le_prenom(): void
    {
        $patient = Patient::factory()->create(['name' => 'Fode Drame']);

        Livewire::actingAs($this->makeReceptionist())
            ->test(PatientLookup::class)
            ->call('select', $patient->getKey())
            ->call('startCorrection', $patient->getKey())
            ->assertSet('correctionLastName', 'Drame')
            ->assertSet('correctionFirstName', 'Fode')
            ->set('correctionFirstName', 'Fode Amadou')
            ->call('saveCorrection')
            ->assertHasNoErrors();

        $this->assertSame('Fode Amadou Drame', $patient->fresh()->name);
    }

    // -------------------------------------------------- La carte au dossier

    public function test_la_carte_d_identite_devient_une_colonne_du_dossier_medical(): void
    {
        $patient = Patient::factory()->create(['id_card_number' => 'AB123456']);

        $dossier = PatientProjection::resolve($patient);

        $this->assertSame('AB123456', $dossier->id_card_number);
    }

    public function test_une_carte_effacee_a_l_accueil_s_efface_au_dossier_medical(): void
    {
        $patient = Patient::factory()->create(['id_card_number' => 'AB123456']);
        $dossier = PatientProjection::resolve($patient);

        app(CorrectPatientIdentity::class)->execute($patient, [
            'last_name' => $patient->last_name,
            'first_name' => $patient->first_name,
            'mobile' => $patient->mobile,
            'gender' => $patient->gender,
            'id_card_number' => '',
        ]);

        $this->assertNull($dossier->fresh()->id_card_number);
    }

    // --------------------------------- L'accompagnateur, personne a prevenir

    public function test_l_accompagnateur_de_l_accueil_devient_la_personne_a_prevenir(): void
    {
        $patient = Patient::factory()->create();

        Companion::create([
            'patient_id' => $patient->getKey(),
            'name' => 'Fatoumata Drame',
            'relation' => 'epouse',
            'phone' => '76998877',
        ]);

        $dossier = PatientProjection::resolve($patient);

        $this->assertDatabaseHas('dme_emergency_contacts', [
            'patient_id' => $dossier->getKey(),
            'name' => 'Fatoumata Drame',
            'relationship' => 'epouse',
            'phone' => '76998877',
            'is_primary' => true,
        ]);
    }

    /**
     * Un accompagnateur sans telephone rejoint quand meme le dossier : un nom
     * et un lien de parente disent deja qui chercher dans la salle d'attente.
     */
    public function test_un_accompagnateur_sans_telephone_rejoint_le_dossier(): void
    {
        $patient = Patient::factory()->create();

        Companion::create([
            'patient_id' => $patient->getKey(),
            'name' => 'Sekou Diarra',
            'relation' => 'fils',
        ]);

        $dossier = PatientProjection::resolve($patient);

        $this->assertDatabaseHas('dme_emergency_contacts', [
            'patient_id' => $dossier->getKey(),
            'name' => 'Sekou Diarra',
            'phone' => null,
        ]);
    }

    /**
     * Deux actes cliniques ne doivent pas porter deux fois la meme personne au
     * dossier : le rapprochement se fait sur le nom.
     */
    public function test_le_meme_accompagnateur_n_est_porte_qu_une_fois(): void
    {
        $patient = Patient::factory()->create();

        Companion::create([
            'patient_id' => $patient->getKey(),
            'name' => 'Fatoumata Drame',
            'relation' => 'epouse',
            'phone' => '76998877',
        ]);

        $dossier = PatientProjection::resolve($patient);
        PatientProjection::resolve($patient->fresh());

        $this->assertSame(1, $dossier->emergencyContacts()->count());
    }

    /**
     * Le sens est unique, mais additif : un contact saisi dans le dossier
     * medical et inconnu de l'accueil n'est pas efface, et il garde son rang.
     */
    public function test_un_contact_propre_au_dossier_medical_survit_a_la_projection(): void
    {
        $patient = Patient::factory()->create();
        $dossier = PatientProjection::resolve($patient);

        $dossier->emergencyContacts()->create([
            'name' => 'Awa Kone',
            'relationship' => 'soeur',
            'phone' => '76001122',
            'is_primary' => true,
        ]);

        Companion::create([
            'patient_id' => $patient->getKey(),
            'name' => 'Sekou Diarra',
            'relation' => 'fils',
        ]);

        PatientProjection::projectCompanions($patient->fresh(), $dossier);

        $this->assertSame(2, $dossier->emergencyContacts()->count());
        $this->assertSame('Awa Kone', $dossier->emergencyContacts()->where('is_primary', true)->value('name'));
    }
}
