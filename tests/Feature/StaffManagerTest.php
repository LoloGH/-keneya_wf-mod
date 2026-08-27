<?php

namespace Tests\Feature;

use App\Livewire\Admin\StaffManager;
use App\Models\Cashier;
use App\Models\Doctor;
use App\Models\Service;
use App\Models\ServiceKind;
use App\Models\StaffMember;
use App\Models\StaffType;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Section « Personnels » (v3.2.3, points 2 et 3).
 *
 * Les deux menus refletent les tables, et c'est le type choisi qui decide du
 * role et du rattachement — sans quoi elargir le menu creerait des comptes
 * incoherents.
 */
class StaffManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    public function test_le_menu_type_de_personnel_liste_tous_les_types(): void
    {
        StaffType::create([
            'name' => 'Infirmier', 'matched_role' => null,
            'slug' => 'infirmier', 'capabilities' => [StaffType::CAP_CARE_TASKS],
        ]);

        $proposes = Livewire::actingAs($this->makeAdmin())
            ->test(StaffManager::class)
            ->viewData('staffTypes')
            ->pluck('name');

        // Les trois types d'origine plus celui cree a la main : le menu ne
        // montrait que « Medecin ».
        foreach (['Medecin', 'Receptionniste', 'Caissier', 'Infirmier'] as $attendu) {
            $this->assertTrue($proposes->contains($attendu), "Le menu doit proposer « {$attendu} ».");
        }

        $this->assertSame(StaffType::count(), $proposes->count());
    }

    public function test_le_menu_service_liste_tous_les_services_caisse_comprise(): void
    {
        $soins = Service::factory()->create(['name' => 'Maternite']);
        $caisse = Service::factory()->caisse()->create(['name' => Service::CAISSE_TICKET]);

        $proposes = Livewire::actingAs($this->makeAdmin())
            ->test(StaffManager::class)
            ->viewData('services')
            ->pluck('id');

        $this->assertTrue($proposes->contains($soins->getKey()));
        $this->assertTrue($proposes->contains($caisse->getKey()));
    }

    public function test_le_type_choisi_decide_du_role_et_de_la_table_de_rattachement(): void
    {
        Livewire::actingAs($this->makeAdmin())
            ->test(StaffManager::class)
            ->set('name', 'Fatoumata Sidibe')
            ->set('email', 'caisse@keneya.test')
            ->set('password', 'motdepasse')
            ->set('staff_type_id', $this->staffTypeFor(Roles::CASHIER)->getKey())
            ->call('save')
            ->assertHasNoErrors();

        $cashier = Cashier::with('user')->firstOrFail();

        // Un caissier etait tout simplement increable depuis /admin : la
        // section « Medecins » n'offrait que le type « Medecin », et un compte
        // cree la aurait porte le role « medecin ».
        $this->assertTrue($cashier->user->hasRole(Roles::CASHIER));
        $this->assertFalse($cashier->user->hasRole(Roles::DOCTOR));
        $this->assertSame(route('caisse.home'), route($cashier->user->homeRoute()));
        $this->assertSame(0, Doctor::count());
    }

    public function test_un_type_sans_role_cree_un_membre_a_interface_generique(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $type = StaffType::create([
            'name' => 'Infirmier', 'matched_role' => null,
            'slug' => 'infirmier', 'capabilities' => [StaffType::CAP_CARE_TASKS],
        ]);

        Livewire::actingAs($this->makeAdmin())
            ->test(StaffManager::class)
            ->set('name', 'Bakary Coulibaly')
            ->set('email', 'infirmier@keneya.test')
            ->set('password', 'motdepasse')
            ->set('staff_type_id', $type->getKey())
            ->set('service_id', $service->getKey())
            ->call('save')
            ->assertHasNoErrors();

        $member = StaffMember::with('user')->firstOrFail();

        // Aucun role Spatie : le cloisonnement d'un type generique se joue sur
        // son slug, pas sur un role.
        $this->assertSame($type->getKey(), $member->staff_type_id);
        $this->assertSame($service->getKey(), $member->service_id);
        $this->assertTrue($member->user->roles->isEmpty());
        $this->assertStringEndsWith('/staff/infirmier', $type->homeUrl());
    }

    public function test_le_service_n_est_demande_qu_a_qui_exerce_dans_un_service(): void
    {
        $composant = Livewire::actingAs($this->makeAdmin())
            ->test(StaffManager::class)
            ->set('staff_type_id', $this->staffTypeFor(Roles::CASHIER)->getKey());

        // Une caissiere n'est pas rattachee a un service : lui en demander un
        // aurait laisse un champ obligatoire sans reponse possible.
        $this->assertFalse($composant->instance()->needsService());
        $this->assertFalse($composant->instance()->needsPhone());

        $composant->set('staff_type_id', $this->staffTypeFor(Roles::DOCTOR)->getKey());

        $this->assertTrue($composant->instance()->needsService());
        $this->assertTrue($composant->instance()->needsPhone());

        $composant
            ->set('name', 'Dr Sans Service')
            ->set('email', 'sans-service@keneya.test')
            ->set('password', 'motdepasse')
            ->call('save')
            ->assertHasErrors('service_id');
    }

    public function test_un_compte_ne_change_pas_de_role_en_changeant_de_type(): void
    {
        $doctor = $this->makeDoctor(Service::factory()->create());

        Livewire::actingAs($this->makeAdmin())
            ->test(StaffManager::class)
            ->call('edit', 'doctor:'.$doctor->getKey())
            ->set('staff_type_id', $this->staffTypeFor(Roles::CASHIER)->getKey())
            ->call('save');

        // Changer de role changerait de table de rattachement, donc
        // d'interface et d'historique : le refus est explicite.
        $this->assertSame(0, Cashier::count());
        $this->assertDatabaseHas('doctors', ['id' => $doctor->getKey()]);
        $this->assertTrue($doctor->user->refresh()->hasRole(Roles::DOCTOR));
    }

    public function test_la_liste_reunit_tout_le_personnel(): void
    {
        $service = Service::factory()->create(['name' => 'Urgences']);
        $doctor = $this->makeDoctor($service);
        $doctor->user->update(['name' => 'Dr Alpha']);

        $receptionniste = $this->makeReceptionist();
        $receptionniste->update(['name' => 'Bintou Accueil']);

        $caissier = $this->makeCashier();
        $caissier->update(['name' => 'Cheick Caisse']);

        $type = StaffType::create([
            'name' => 'Infirmier', 'matched_role' => null,
            'slug' => 'infirmier', 'capabilities' => [StaffType::CAP_CARE_TASKS],
        ]);
        StaffMember::create([
            'user_id' => User::factory()->create(['name' => 'Djeneba Soins'])->getKey(),
            'staff_type_id' => $type->getKey(),
            'service_id' => $service->getKey(),
        ]);

        $rendu = Livewire::actingAs($this->makeAdmin())->test(StaffManager::class);

        // Une seule liste : l'admin n'a plus a deviner dans quelle section
        // chercher une personne.
        $rendu->assertSee('Dr Alpha')
            ->assertSee('Bintou Accueil')
            ->assertSee('Cheick Caisse')
            ->assertSee('Djeneba Soins');

        $this->assertSame(4, $rendu->viewData('personnels')->count());
    }

    public function test_la_section_s_appelle_personnels_dans_le_menu_de_l_admin(): void
    {
        $reponse = $this->actingAs($this->makeAdmin())->get('/admin');

        $reponse->assertOk()->assertSee('Personnels');
        $reponse->assertDontSee('>Medecins<', false);
    }

    public function test_le_type_de_service_reste_lisible_dans_le_menu_service(): void
    {
        $kind = ServiceKind::create(['name' => 'Imagerie', 'slug' => 'imagerie']);
        Service::factory()->ofKind($kind)->create(['name' => 'Scanner']);

        Livewire::actingAs($this->makeAdmin())
            ->test(StaffManager::class)
            ->set('staff_type_id', $this->staffTypeFor(Roles::DOCTOR)->getKey())
            ->assertSee('Scanner (Imagerie)');
    }
}
