<?php

namespace Tests\Feature;

use App\Actions\CorrectPatientIdentity;
use App\Livewire\Reception\PatientLookup;
use App\Livewire\Reception\PatientRegistrationForm;
use App\Livewire\Reception\VisitorRegistrationForm;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Visitor;
use App\Services\DuplicatePatientFinder;
use App\Services\SmsGateway;
use App\Services\SmsSendResult;
use App\Support\Dme\PatientProjection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Le numero de la carte d'identite (v3.3.1).
 *
 * C'est le seul champ qui distingue deux personnes a coup sur : le telephone
 * change et se prete, le nom s'ecrit de dix facons, l'age se donne a un an
 * pres. La recherche de doublon travaillait sur des indices, et laissait
 * passer la meme personne sous deux dossiers.
 *
 * Il reste **facultatif**, et ces tests le verifient autant que sa presence :
 * un patient arrive aux urgences sans papiers doit etre enregistre quand meme.
 */
class IdentityCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->mock(SmsGateway::class)->shouldReceive('deliver')->andReturn(SmsSendResult::sent());
    }

    // ------------------------------------------------ Le champ, a la saisie

    public function test_le_formulaire_patient_porte_le_champ_et_l_annonce_facultatif(): void
    {
        Livewire::actingAs($this->makeReceptionist())
            ->test(PatientRegistrationForm::class)
            ->assertSee('carte d\'identite', escape: false)
            ->assertSee('(facultatif)');
    }

    public function test_le_formulaire_visiteur_porte_le_champ(): void
    {
        Livewire::actingAs($this->makeReceptionist())
            ->test(VisitorRegistrationForm::class)
            ->assertSee('carte d\'identite', escape: false);
    }

    public function test_un_patient_s_enregistre_avec_sa_carte(): void
    {
        $service = Service::factory()->create();

        Livewire::actingAs($this->makeReceptionist())
            ->test(PatientRegistrationForm::class)
            ->set('name', 'Fode Drame')
            ->set('age', 33)
            ->set('gender', 'Homme')
            ->set('mobile', '76445566')
            ->set('idCardNumber', 'AB 123 456')
            ->set('service_id', $service->getKey())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('patients', [
            'name' => 'Fode Drame',
            'id_card_number' => 'AB 123 456',
        ]);
    }

    /**
     * La verification qui compte : sans carte, l'enregistrement passe. Un
     * patient arrive sans papiers ne doit pas rester a la porte.
     */
    public function test_un_patient_sans_carte_s_enregistre_quand_meme(): void
    {
        $service = Service::factory()->create();

        Livewire::actingAs($this->makeReceptionist())
            ->test(PatientRegistrationForm::class)
            ->set('name', 'Aminata Traore')
            ->set('age', 41)
            ->set('gender', 'Femme')
            ->set('mobile', '76998877')
            ->set('service_id', $service->getKey())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('patients', [
            'name' => 'Aminata Traore',
            'id_card_number' => null,
        ]);
    }

    public function test_un_visiteur_s_enregistre_avec_sa_carte(): void
    {
        $service = Service::factory()->create();
        $visite = Patient::factory()->create();

        Livewire::actingAs($this->makeReceptionist())
            ->test(VisitorRegistrationForm::class)
            ->set('name', 'Sekou Diarra')
            ->set('service_id', $service->getKey())
            ->set('patient_id', $visite->getKey())
            ->set('idCardNumber', 'CD-789-012')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('CD-789-012', Visitor::firstOrFail()->id_card_number);
    }

    // --------------------------------------------------- Le doublon evite

    /**
     * Deux personnes ne partagent pas une carte d'identite : quand elle est
     * la, elle tranche, avant meme le telephone.
     */
    public function test_la_carte_retrouve_un_dossier_existant(): void
    {
        $ancien = Patient::factory()->create([
            'name' => 'Fode Drame',
            'mobile' => '76445566',
            'id_card_number' => 'AB123456',
        ]);

        // Nom different, telephone different : seule la carte rapproche.
        $trouves = app(DuplicatePatientFinder::class)->search(
            mobile: '76000000',
            name: 'F. Drame',
            age: 50,
            idCardNumber: 'ab 123-456',
        );

        $this->assertTrue($trouves->contains($ancien), 'La carte n\'a pas retrouve le dossier.');
    }

    public function test_l_enregistrement_propose_le_dossier_de_la_meme_carte(): void
    {
        $service = Service::factory()->create();
        $ancien = Patient::factory()->create([
            'name' => 'Fode Drame',
            'mobile' => '76445566',
            'id_card_number' => 'AB123456',
        ]);

        Livewire::actingAs($this->makeReceptionist())
            ->test(PatientRegistrationForm::class)
            ->set('name', 'Fode Drame')
            ->set('age', 33)
            ->set('gender', 'Homme')
            ->set('mobile', '76112233')
            ->set('idCardNumber', 'AB 123 456')
            ->set('service_id', $service->getKey())
            ->call('save')
            ->assertSee($ancien->patient_code);

        // Rien n'est cree tant que la receptionniste n'a pas tranche.
        $this->assertSame(1, Patient::count());
    }

    /**
     * Une carte vide ne rapproche rien : sans cela, tous les dossiers sans
     * carte se ressembleraient.
     */
    public function test_une_carte_vide_ne_rapproche_aucun_dossier(): void
    {
        Patient::factory()->count(3)->create(['id_card_number' => null]);

        $this->assertTrue(
            app(DuplicatePatientFinder::class)->byIdCard(null)->isEmpty(),
            'Une carte absente ne doit rapprocher aucun dossier.',
        );

        $this->assertTrue(app(DuplicatePatientFinder::class)->byIdCard('  ')->isEmpty());
    }

    // ------------------------------------------------------- La correction

    /**
     * Corriger une identite n'est pas en creer une seconde : le numero de
     * dossier ne change jamais.
     */
    public function test_la_correction_ne_change_jamais_le_numero_de_dossier(): void
    {
        $patient = Patient::factory()->create([
            'name' => 'Fode Drame',
            'mobile' => '76445566',
            'gender' => 'Homme',
        ]);

        $code = $patient->patient_code;

        app(CorrectPatientIdentity::class)->execute($patient, [
            'name' => 'Fode Drame Junior',
            'mobile' => '76998877',
            'profession' => 'Informaticien',
            'gender' => 'Homme',
            'id_card_number' => 'AB123456',
            // Glisse dans la requete : il ne doit pas passer.
            'patient_code' => 'HFD-99999',
            'age' => 99,
        ]);

        $patient->refresh();

        $this->assertSame($code, $patient->patient_code);
        $this->assertSame('Fode Drame Junior', $patient->name);
        $this->assertSame('AB123456', $patient->id_card_number);
        $this->assertNotSame(99, $patient->age);
    }

    public function test_l_accueil_corrige_l_identite_depuis_la_recherche(): void
    {
        $patient = Patient::factory()->create([
            'name' => 'Fode Drame',
            'mobile' => '76445566',
            'gender' => 'Homme',
        ]);

        Livewire::actingAs($this->makeReceptionist())
            ->test(PatientLookup::class)
            ->call('select', $patient->getKey())
            ->call('startCorrection', $patient->getKey())
            ->assertSet('correctionName', 'Fode Drame')
            ->set('correctionIdCardNumber', 'AB123456')
            ->call('saveCorrection')
            ->assertHasNoErrors();

        $this->assertSame('AB123456', $patient->fresh()->id_card_number);
    }

    /**
     * La carte se retire aussi : un numero saisi par erreur ne doit pas rester
     * au dossier.
     */
    public function test_la_carte_se_retire(): void
    {
        $patient = Patient::factory()->create(['id_card_number' => 'AB123456']);

        app(CorrectPatientIdentity::class)->execute($patient, [
            'name' => $patient->name,
            'mobile' => $patient->mobile,
            'gender' => $patient->gender,
            'id_card_number' => '',
        ]);

        $this->assertNull($patient->fresh()->id_card_number);
    }

    // ------------------------------------------------- La projection au DME

    /**
     * La carte se projette dans le dossier medical, sous son propre systeme
     * d'identification : c'est ce qui permettra de reconnaitre la meme
     * personne au-dela du numero de dossier WorkFlow.
     */
    public function test_la_carte_se_projette_dans_le_dossier_medical(): void
    {
        $patient = Patient::factory()->create(['id_card_number' => 'AB123456']);

        $dossier = PatientProjection::resolve($patient);

        $this->assertDatabaseHas('dme_patient_identifiers', [
            'patient_id' => $dossier->getKey(),
            'system' => PatientProjection::SYSTEM_CARTE,
            'value' => 'AB123456',
        ]);
    }

    public function test_une_carte_retiree_retire_son_identifiant_au_dossier(): void
    {
        $patient = Patient::factory()->create(['id_card_number' => 'AB123456']);
        $dossier = PatientProjection::resolve($patient);

        app(CorrectPatientIdentity::class)->execute($patient, [
            'name' => $patient->name,
            'mobile' => $patient->mobile,
            'gender' => $patient->gender,
            'id_card_number' => '',
        ]);

        $this->assertDatabaseMissing('dme_patient_identifiers', [
            'patient_id' => $dossier->getKey(),
            'system' => PatientProjection::SYSTEM_CARTE,
        ]);
    }
}
