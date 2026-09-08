<?php

namespace Tests\Feature;

use App\Livewire\Service\MedicalBackground;
use App\Models\Patient;
use App\Models\Service;
use App\Models\StaffType;
use App\Models\User;
use App\Models\Visit;
use App\Support\Roles;
use Database\Seeders\DmePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Keneya\Dme\Models\Allergy;
use Keneya\Dme\Models\Patient as DossierMedical;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Antécédents et allergies, saisis depuis /service (v3.3.1).
 *
 * Deux formulaires sur un écran, mais deux capacités distinctes : le fil
 * conducteur de ces tests est qu'on peut confier l'une sans l'autre.
 */
class MedicalBackgroundTest extends TestCase
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

    // ------------------------------------------------------- Les capacités

    public function test_les_deux_moities_de_l_ecran_se_confient_separement(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele([StaffType::CAP_RECORD_ALLERGIES]);

        $ecran = Livewire::actingAs($medecin)
            ->test(MedicalBackground::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey());

        // Seules les allergies lui reviennent : le bloc des antecedents n'est
        // meme pas rendu.
        $ecran->assertSee('Allergies connues')->assertDontSee('Consigner l\'antecedent');

        // Et le formulaire refuse cote serveur, pas seulement a l'affichage.
        $ecran->set('label', 'Hypertension arterielle')->call('saveHistory')->assertForbidden();

        $this->assertDatabaseCount('dme_medical_histories', 0);
    }

    public function test_une_allergie_est_refusee_sans_sa_capacite(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele([StaffType::CAP_RECORD_HISTORY]);

        Livewire::actingAs($medecin)
            ->test(MedicalBackground::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('allergen', 'Penicilline')
            ->set('severity', 'severe')
            ->call('saveAllergy')
            ->assertForbidden();

        $this->assertDatabaseCount('dme_allergies', 0);
    }

    /**
     * Saisir dans le dossier medical n'exige pas le droit de l'ouvrir.
     *
     * Ce sont deux droits distincts, et c'est voulu : une infirmiere peut
     * avoir a relever une allergie sans qu'on lui confie la lecture de
     * l'antecedent medical du patient. `can_access_dme` ouvre la porte du
     * module ; les capacites `can_record_*` autorisent un acte precis depuis
     * /service, sans jamais donner acces au reste du dossier.
     */
    public function test_ecrire_au_dossier_n_exige_pas_le_droit_de_l_ouvrir(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele([
            StaffType::CAP_RECORD_HISTORY, StaffType::CAP_RECORD_ALLERGIES,
        ]);

        $this->assertFalse($medecin->canAccessDme(), 'Le compte ne doit pas avoir acces au module.');

        Livewire::actingAs($medecin)
            ->test(MedicalBackground::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('allergen', 'Penicilline')
            ->set('severity', 'severe')
            ->call('saveAllergy')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('dme_allergies', ['allergen' => 'Penicilline']);

        // Et la porte du module lui reste bien fermee.
        $this->actingAs($medecin)->get(route('dme.home'))->assertForbidden();
    }

    // ---------------------------------------------------------- Antécédents

    public function test_un_antecedent_atterrit_dans_le_dossier_medical(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele([StaffType::CAP_RECORD_HISTORY]);

        Livewire::actingAs($medecin)
            ->test(MedicalBackground::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('category', 'surgical')
            ->set('label', 'Appendicectomie')
            ->set('year', '2019')
            ->set('facility', 'Hopital de Kayes')
            ->call('saveHistory')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('dme_medical_histories', [
            'category' => 'surgical',
            'label' => 'Appendicectomie',
            'year' => '2019',
            'facility' => 'Hopital de Kayes',
            'recorded_by' => $medecin->getKey(),
        ]);

        // Le dossier medical se cree au premier acte, ici comme ailleurs.
        $this->assertDatabaseCount('dme_patients', 1);
    }

    public function test_le_lien_de_parente_ne_survit_pas_a_un_changement_de_categorie(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele([StaffType::CAP_RECORD_HISTORY]);

        // Le medecin renseigne un antecedent familial, se ravise, et bascule
        // sur « personnels » sans vider le champ : le lien de parente ne doit
        // pas suivre. Sans cela, le dossier se remplit de valeurs heritees
        // d'une saisie precedente.
        Livewire::actingAs($medecin)
            ->test(MedicalBackground::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('category', 'family')
            ->set('relative', 'Mere')
            ->set('category', 'personal')
            ->set('label', 'Asthme')
            ->call('saveHistory')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('dme_medical_histories', [
            'category' => 'personal',
            'label' => 'Asthme',
            'relative' => null,
        ]);
    }

    public function test_une_annee_hors_bornes_est_refusee(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele([StaffType::CAP_RECORD_HISTORY]);

        Livewire::actingAs($medecin)
            ->test(MedicalBackground::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('label', 'Fracture')
            ->set('year', '2199')
            ->call('saveHistory')
            ->assertHasErrors('year');

        $this->assertDatabaseCount('dme_medical_histories', 0);
    }

    // ------------------------------------------------------------ Allergies

    public function test_une_allergie_atterrit_dans_le_dossier_medical(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele([StaffType::CAP_RECORD_ALLERGIES]);

        Livewire::actingAs($medecin)
            ->test(MedicalBackground::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('allergen', 'Penicilline')
            ->set('allergenType', 'medication')
            ->set('reaction', 'Urticaire generalisee')
            ->set('severity', 'severe')
            ->call('saveAllergy')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('dme_allergies', [
            'allergen' => 'Penicilline',
            'allergen_type' => 'medication',
            'severity' => 'severe',
            'status' => 'active',
            'recorded_by' => $medecin->getKey(),
        ]);
    }

    /**
     * La sévérité est ce que le module relit pour alerter au moment de
     * prescrire : elle ne doit pas pouvoir rester vide par inadvertance.
     */
    public function test_la_severite_est_exigee(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele([StaffType::CAP_RECORD_ALLERGIES]);

        Livewire::actingAs($medecin)
            ->test(MedicalBackground::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('allergen', 'Penicilline')
            ->set('severity', '')
            ->call('saveAllergy')
            ->assertHasErrors('severity');

        $this->assertDatabaseCount('dme_allergies', 0);
    }

    public function test_une_allergie_se_refute_mais_ne_se_supprime_pas(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele([StaffType::CAP_RECORD_ALLERGIES]);

        $ecran = Livewire::actingAs($medecin)
            ->test(MedicalBackground::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('allergen', 'Arachide')
            ->set('severity', 'moderate')
            ->call('saveAllergy');

        $allergie = Allergy::firstOrFail();

        $ecran->call('setAllergyStatus', $allergie->getKey(), 'refuted');

        // La ligne demeure : une allergie evoquee puis ecartee est une
        // information clinique, l'effacer ferait refaire le meme cheminement
        // au prochain medecin.
        $this->assertDatabaseCount('dme_allergies', 1);
        $this->assertSame('refuted', $allergie->fresh()->status);
    }

    public function test_l_allergie_d_un_autre_patient_reste_hors_de_portee(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele([StaffType::CAP_RECORD_ALLERGIES]);

        // Une allergie appartenant a un dossier que ce passage ne designe pas.
        $autreDossier = DossierMedical::create(['last_name' => 'Diallo', 'first_name' => 'Salif']);
        $etrangere = $autreDossier->allergies()->create([
            'allergen' => 'Iode', 'severity' => 'severe', 'status' => 'active',
        ]);

        Livewire::actingAs($medecin)
            ->test(MedicalBackground::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->call('setAllergyStatus', $etrangere->getKey(), 'refuted')
            ->assertNotFound();

        $this->assertSame('active', $etrangere->fresh()->status);
    }

    public function test_ouvrir_l_ecran_ne_cree_aucun_dossier_medical(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele([
            StaffType::CAP_RECORD_HISTORY, StaffType::CAP_RECORD_ALLERGIES,
        ]);

        Livewire::actingAs($medecin)
            ->test(MedicalBackground::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->assertOk();

        // Consulter n'est pas ecrire : le dossier nait a la premiere saisie,
        // pas au premier regard.
        $this->assertDatabaseCount('dme_patients', 0);
    }
}
