<?php

namespace Tests\Feature;

use App\Livewire\Admin\StaffTypeManager;
use App\Livewire\Service\ConsultationActions;
use App\Livewire\Service\Hospitalizations;
use App\Livewire\Service\MyAppointments;
use App\Models\Service;
use App\Models\StaffType;
use App\Models\User;
use App\Models\Visit;
use App\Services\SmsGateway;
use App\Services\SmsSendResult;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Capacites obligatoires et optionnelles, pour tous les types (v3.2.2).
 */
class StaffTypeCapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(SmsGateway::class)->shouldReceive('deliver')->andReturn(SmsSendResult::sent());
    }

    private function builtInType(string $role): StaffType
    {
        return StaffType::where('matched_role', $role)->orderBy('id')->firstOrFail();
    }

    // ------------------------- Le defaut d'edition corrige

    public function test_modifier_un_type_adosse_a_un_role_n_efface_plus_ses_capacites(): void
    {
        $type = StaffType::create([
            'name' => 'Aide-soignant',
            'matched_role' => null,
            'slug' => 'aide-soignant',
            'capabilities' => [StaffType::CAP_QUEUE, StaffType::CAP_VIEW_DOSSIER, StaffType::CAP_CARE_TASKS],
        ]);

        // Avant le v3.2.2, basculer ce type vers un role remettait
        // `capabilities` a null : les fonctions cochees etaient perdues sans
        // avertissement, et revenir en arriere ne les rendait pas.
        Livewire::actingAs($this->makeAdmin())
            ->test(StaffTypeManager::class)
            ->call('edit', $type->getKey())
            ->set('matched_role', Roles::DOCTOR)
            ->call('save')
            ->assertHasNoErrors();

        $type->refresh();

        $this->assertNotNull($type->capabilities, 'Les capacites ne doivent plus etre effacees.');
        // La capacite optionnelle compatible avec le role survit…
        $this->assertTrue($type->can(StaffType::CAP_CARE_TASKS));
        // …et les obligatoires du role sont posees.
        foreach ($type->requiredCapabilities() as $obligatoire) {
            $this->assertTrue($type->can($obligatoire));
        }
    }

    public function test_le_formulaire_d_edition_propose_des_cases_pour_un_type_adosse(): void
    {
        $medecin = $this->builtInType(Roles::DOCTOR);

        $rendu = Livewire::actingAs($this->makeAdmin())
            ->test(StaffTypeManager::class)
            ->call('edit', $medecin->getKey())
            ->html();

        // Une obligatoire, verrouillee ; une optionnelle, modifiable.
        // `e()` : Blade echappe l'apostrophe des libelles.
        $this->assertStringContainsString(e(StaffType::CAPABILITIES[StaffType::CAP_QUEUE]['label']), $rendu);
        $this->assertStringContainsString('Indispensable au fonctionnement de ce role', $rendu);
        $this->assertStringContainsString(e(StaffType::CAPABILITIES[StaffType::CAP_PRESCRIBE]['label']), $rendu);
        // Le message qui annoncait qu'il n'y avait rien a cocher a disparu.
        $this->assertStringNotContainsString("Aucune fonction n'est a cocher", $rendu);
    }

    // ------------------------- Obligatoires : indecochables

    public function test_une_capacite_obligatoire_ne_peut_pas_etre_decochee(): void
    {
        $medecin = $this->builtInType(Roles::DOCTOR);

        // Requete forgee : on soumet une liste amputee de toutes les
        // obligatoires. Le serveur les remet, il ne se fie pas au client.
        Livewire::actingAs($this->makeAdmin())
            ->test(StaffTypeManager::class)
            ->call('edit', $medecin->getKey())
            ->set('capabilities', [StaffType::CAP_PRESCRIBE])
            ->call('save')
            ->assertHasNoErrors();

        $medecin->refresh();

        foreach (StaffType::ROLE_CAPABILITIES[Roles::DOCTOR]['required'] as $obligatoire) {
            $this->assertTrue(
                $medecin->can($obligatoire),
                "La capacite obligatoire {$obligatoire} ne doit pas pouvoir etre retiree.",
            );
        }
    }

    public function test_une_capacite_hors_perimetre_du_role_est_ecartee(): void
    {
        $caissier = $this->builtInType(Roles::CASHIER);

        Livewire::actingAs($this->makeAdmin())
            ->test(StaffTypeManager::class)
            ->call('edit', $caissier->getKey())
            // « Hospitaliser » n'appartient pas au role caissier.
            ->set('capabilities', [StaffType::CAP_ADMIT_HOSPITALIZATION])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($caissier->refresh()->can(StaffType::CAP_ADMIT_HOSPITALIZATION));
    }

    public function test_une_capacite_obligatoire_repond_oui_meme_sans_etre_stockee(): void
    {
        // Une base anterieure au v3.2.2 a `capabilities` a null : l'interface
        // ne doit pas s'en trouver amputee.
        $type = StaffType::create(['name' => 'Ancien medecin', 'matched_role' => Roles::DOCTOR]);

        $this->assertTrue($type->can(StaffType::CAP_QUEUE));
        $this->assertFalse($type->can(StaffType::CAP_PRESCRIBE));
    }

    public function test_un_type_sans_role_n_impose_rien(): void
    {
        $type = StaffType::create([
            'name' => 'Infirmier', 'matched_role' => null,
            'slug' => 'infirmier', 'capabilities' => [StaffType::CAP_CARE_TASKS],
        ]);

        $this->assertSame([], $type->requiredCapabilities());
        $this->assertSame(array_keys(StaffType::CAPABILITIES), $type->optionalCapabilities());
    }

    // ------------------------- La colonne « Fonctions »

    public function test_la_colonne_fonctions_ne_montre_plus_de_tiret(): void
    {
        $rendu = Livewire::actingAs($this->makeAdmin())->test(StaffTypeManager::class)->html();

        $medecin = $this->builtInType(Roles::DOCTOR);
        $attendu = count(StaffType::ROLE_CAPABILITIES[Roles::DOCTOR]['optional']);

        $this->assertSame($attendu, count($medecin->enabledOptionalCapabilities()));
        $this->assertStringContainsString('/ '.$attendu, $rendu);
    }

    // ------------------------- Aucune regression sur la demo

    public function test_les_types_d_origine_gardent_toutes_leurs_options_apres_migration(): void
    {
        foreach (StaffType::ROLE_CAPABILITIES as $role => $capacites) {
            $type = $this->builtInType($role);

            foreach (array_merge($capacites['required'], $capacites['optional']) as $capacite) {
                $this->assertTrue(
                    $type->can($capacite),
                    "Le type d'origine « {$type->name} » doit conserver {$capacite}.",
                );
            }
        }
    }

    public function test_un_compte_de_demonstration_retrouve_son_type_sans_rattachement_explicite(): void
    {
        $doctor = $this->makeDoctor(Service::factory()->create());

        // Pas de staff_type_id : la resolution retombe sur le type du role.
        $this->assertNull($doctor->user->staff_type_id);
        $this->assertSame($this->builtInType(Roles::DOCTOR)->getKey(), $doctor->user->staffType()?->getKey());
        $this->assertTrue($doctor->user->hasCapability(StaffType::CAP_PRESCRIBE));
    }

    // ------------------------- Effet reel sur /service

    /** Un medecin dont le type n'a plus la capacite donnee. */
    private function makeDoctorWithout(string $capacite, Service $service): User
    {
        $type = StaffType::create([
            'name' => 'Medecin restreint',
            'matched_role' => Roles::DOCTOR,
        ]);
        $type->update([
            'capabilities' => $type->normalizeCapabilities(
                array_values(array_diff(StaffType::ROLE_CAPABILITIES[Roles::DOCTOR]['optional'], [$capacite])),
            ),
        ]);

        $doctor = $this->makeDoctor($service);
        $doctor->user->update(['staff_type_id' => $type->getKey()]);

        return $doctor->user->refresh();
    }

    public function test_un_medecin_sans_la_capacite_ne_voit_plus_la_section(): void
    {
        $service = Service::factory()->create();
        $user = $this->makeDoctorWithout(StaffType::CAP_SCHEDULE_APPOINTMENT, $service);

        $this->assertFalse($user->hasCapability(StaffType::CAP_SCHEDULE_APPOINTMENT));

        $this->actingAs($user)
            ->get('/service')
            ->assertOk()
            ->assertDontSee('Mes rendez-vous')
            // Les sections qui tiennent au role restent la.
            ->assertSee("File d'attente", escape: false)
            ->assertSee('Mes patients');
    }

    public function test_un_medecin_garde_ses_sections_quand_la_capacite_est_activee(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);

        $this->actingAs($doctor->user)
            ->get('/service')
            ->assertOk()
            ->assertSee('Mes rendez-vous')
            ->assertSee('Fin de consultation')
            ->assertSee('Patients hospitalises');
    }

    public function test_la_section_masquee_reste_inatteignable_par_appel_direct(): void
    {
        $service = Service::factory()->create();
        $user = $this->makeDoctorWithout(StaffType::CAP_SCHEDULE_APPOINTMENT, $service);

        // Masquer une section ne suffit pas : le composant se refuse aussi.
        Livewire::actingAs($user)
            ->test(MyAppointments::class)
            ->assertStatus(403);
    }

    public function test_une_action_hors_capacite_est_refusee_cote_serveur(): void
    {
        $service = Service::factory()->create();
        $user = $this->makeDoctorWithout(StaffType::CAP_PRESCRIBE, $service);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        Livewire::actingAs($user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('prescriptionLines.0.medicament', 'Paracetamol 500 mg')
            ->call('savePrescription')
            ->assertStatus(403);
    }

    public function test_l_hospitalisation_se_retire_avec_sa_capacite(): void
    {
        $service = Service::factory()->create();
        $user = $this->makeDoctorWithout(StaffType::CAP_ADMIT_HOSPITALIZATION, $service);

        $this->actingAs($user)->get('/service')->assertDontSee('Patients hospitalises');

        Livewire::actingAs($user)
            ->test(Hospitalizations::class, ['serviceId' => $service->getKey()])
            ->assertStatus(403);
    }

    // ------------------------- Effet reel sur /reception

    public function test_une_receptionniste_sans_la_capacite_ne_voit_plus_la_section_visiteur(): void
    {
        $type = StaffType::create(['name' => 'Accueil simple', 'matched_role' => Roles::RECEPTIONIST]);
        $type->update(['capabilities' => $type->normalizeCapabilities([])]);

        $user = $this->makeReceptionist();
        $user->update(['staff_type_id' => $type->getKey()]);

        $this->actingAs($user->refresh())
            ->get('/reception')
            ->assertOk()
            ->assertDontSee('Visiteur')
            ->assertDontSee('Rendez-vous du jour')
            ->assertSee('Nouveau patient');
    }
}
