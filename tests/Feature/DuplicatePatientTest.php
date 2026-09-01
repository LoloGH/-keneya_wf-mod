<?php

namespace Tests\Feature;

use App\Livewire\Reception\PatientRegistrationForm;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Visit;
use App\Support\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Doublons de patients a l'enregistrement (v3.2.8, point 1).
 *
 * Rien ne verifiait, avant, qu'un patient n'etait pas deja en base : la meme
 * personne pouvait repartir avec deux `patient_code`, ce qui contredit la
 * promesse centrale du produit.
 */
class DuplicatePatientTest extends TestCase
{
    use RefreshDatabase;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        Queue::fake();

        $this->service = Service::factory()->create(['name' => 'Medecine Generale']);
    }

    /**
     * Le scenario signale, a l'identique : meme nom, meme age, meme profession,
     * meme telephone qu'un patient deja en base.
     */
    public function test_le_scenario_signale_declenche_la_detection_sans_creer_de_second_code(): void
    {
        $existant = Patient::factory()->create([
            'name' => 'Fatoumata Diarra',
            'age' => 29,
            'profession' => 'Commercante',
            'mobile' => '76445566',
        ]);

        Livewire::actingAs($this->makeReceptionist())
            ->test(PatientRegistrationForm::class)
            ->set('name', 'Fatoumata Diarra')
            ->set('age', 29)
            ->set('gender', 'Femme')
            ->set('profession', 'Commercante')
            ->set('mobile', '76445566')
            ->set('service_id', $this->service->getKey())
            ->call('save')
            ->assertSet('duplicateCandidates.0.patient_code', $existant->patient_code);

        // Le point qui compte : aucun second dossier n'a ete cree.
        $this->assertSame(1, Patient::count());
        $this->assertSame(0, Visit::count());
    }

    /**
     * Le telephone est le champ le plus fiable : il doit suffire, meme quand le
     * nom est ecrit autrement — c'est le cas le plus courant en pratique.
     */
    public function test_un_nom_orthographie_autrement_est_rattrape_par_le_telephone(): void
    {
        $existant = Patient::factory()->create([
            'name' => 'Oumar Cisse',
            'age' => 40,
            'mobile' => '76445566',
        ]);

        Livewire::actingAs($this->makeReceptionist())
            ->test(PatientRegistrationForm::class)
            ->set('name', 'Oumar Sissoko')
            ->set('age', 41)
            ->set('gender', 'Homme')
            // Meme numero, saisi avec des espaces et l'indicatif pays.
            ->set('mobile', '+223 76 44 55 66')
            ->set('service_id', $this->service->getKey())
            ->call('save')
            ->assertSet('duplicateCandidates.0.patient_code', $existant->patient_code);

        $this->assertSame(1, Patient::count());
    }

    /**
     * Seconde passe : le patient a change de numero, mais son nom et son age
     * le designent toujours.
     */
    public function test_un_changement_de_numero_est_rattrape_par_le_nom_et_l_age(): void
    {
        $existant = Patient::factory()->create([
            'name' => 'Aminata Kone',
            'age' => 35,
            'mobile' => '76111111',
        ]);

        Livewire::actingAs($this->makeReceptionist())
            ->test(PatientRegistrationForm::class)
            ->set('name', 'aminata kone')
            ->set('age', 36)
            ->set('gender', 'Femme')
            ->set('mobile', '70999999')
            ->set('service_id', $this->service->getKey())
            ->call('save')
            ->assertSet('duplicateCandidates.0.patient_code', $existant->patient_code);

        $this->assertSame(1, Patient::count());
    }

    /** Un patient reellement nouveau ne doit rien declencher. */
    public function test_un_patient_inconnu_est_enregistre_sans_interruption(): void
    {
        Patient::factory()->create(['name' => 'Oumar Cisse', 'age' => 40, 'mobile' => '76111111']);

        Livewire::actingAs($this->makeReceptionist())
            ->test(PatientRegistrationForm::class)
            ->set('name', 'Salif Traore')
            ->set('age', 22)
            ->set('gender', 'Homme')
            ->set('mobile', '70223344')
            ->set('service_id', $this->service->getKey())
            ->call('save')
            ->assertSet('duplicateCandidates', []);

        $this->assertSame(2, Patient::count());
        $this->assertSame(1, Visit::count());
    }

    /**
     * « C'est la meme personne » : un nouvel episode s'ouvre sur le dossier
     * existant, par le mecanisme de reprise deja en place.
     */
    public function test_confirmer_la_meme_personne_ouvre_un_episode_sur_le_dossier_existant(): void
    {
        $existant = Patient::factory()->create([
            'name' => 'Fatoumata Diarra',
            'age' => 29,
            'mobile' => '76445566',
        ]);

        Livewire::actingAs($this->makeReceptionist())
            ->test(PatientRegistrationForm::class)
            ->set('name', 'Fatoumata Diarra')
            ->set('age', 29)
            ->set('gender', 'Femme')
            ->set('mobile', '76445566')
            ->set('service_id', $this->service->getKey())
            ->call('save')
            ->call('openEpisodeForExisting', $existant->getKey())
            ->assertSet('duplicateCandidates', []);

        // Aucune identite creee, un passage ouvert sous le code existant.
        $this->assertSame(1, Patient::count());
        $this->assertSame(1, Visit::count());
        $this->assertSame($existant->getKey(), Visit::sole()->patient_id);
    }

    /**
     * « C'est une personne differente » : la creation aboutit — on n'empeche
     * pas la receptionniste — mais la decision est inscrite au journal, avec le
     * dossier qui lui avait ete propose.
     */
    public function test_forcer_la_creation_est_possible_et_journalise(): void
    {
        $existant = Patient::factory()->create([
            'name' => 'Fatoumata Diarra',
            'age' => 29,
            'mobile' => '76445566',
        ]);

        $receptionniste = $this->makeReceptionist();

        Livewire::actingAs($receptionniste)
            ->test(PatientRegistrationForm::class)
            ->set('name', 'Fatoumata Diarra')
            ->set('age', 29)
            ->set('gender', 'Femme')
            ->set('mobile', '76445566')
            ->set('service_id', $this->service->getKey())
            ->call('save')
            ->call('createAnyway')
            ->assertSet('duplicateCandidates', [])
            ->assertSet('forceCreation', false);

        $this->assertSame(2, Patient::count());

        $trace = Activity::where('event', Audit::EVENT_DUPLICATE_OVERRIDDEN)->sole();

        $this->assertSame($receptionniste->getKey(), $trace->causer_id);
        $this->assertStringContainsString($existant->patient_code, $trace->description);
    }
}
