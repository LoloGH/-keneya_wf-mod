<?php

namespace Tests\Feature;

use App\Actions\BulkCreateSchedule;
use App\Livewire\Admin\BulkScheduleForm;
use App\Livewire\Admin\ScheduleManager;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\StaffMember;
use App\Models\StaffType;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Le personnel proposé aux plannings, et la suppression groupée (v3.2.4).
 *
 * Les deux menus de « Plannings » interrogeaient les rôles Spatie. Un type de
 * personnel sans rôle — un infirmier — n'en porte aucun : il était introuvable,
 * donc impossible à mettre de garde. Or c'est le planning qui décide de sa
 * garde, donc de tout ce qu'il voit. C'est la cause réelle des « soins
 * programmés invisibles côté infirmier ».
 */
class ScheduleStaffCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    private function makeNurse(Service $service, string $name = 'Bakary Coulibaly'): User
    {
        $type = StaffType::firstOrCreate(
            ['slug' => 'infirmier'],
            ['name' => 'Infirmier', 'matched_role' => null, 'capabilities' => [StaffType::CAP_CARE_TASKS]],
        );

        $user = User::factory()->create(['name' => $name]);

        StaffMember::create([
            'user_id' => $user->getKey(),
            'staff_type_id' => $type->getKey(),
            'service_id' => $service->getKey(),
        ]);

        return $user;
    }

    // ------------------------------------------ Qui apparait dans les menus

    public function test_le_personnel_generique_est_proposable_a_un_planning(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $infirmier = $this->makeNurse($service);

        foreach ([BulkScheduleForm::class, ScheduleManager::class] as $composant) {
            $propose = Livewire::actingAs($this->makeAdmin())
                ->test($composant)
                ->viewData('staff')
                ->pluck('id');

            $this->assertTrue(
                $propose->contains($infirmier->getKey()),
                $composant.' doit proposer le personnel a interface dediee.',
            );
        }
    }

    public function test_le_caissier_est_proposable_dans_les_deux_formulaires(): void
    {
        $caissier = $this->makeCashier();

        // La generation groupee le proposait deja ; le formulaire jour par jour
        // et le filtre l'oubliaient, alors qu'un caissier a un planning comme
        // les autres.
        foreach ([BulkScheduleForm::class, ScheduleManager::class] as $composant) {
            $propose = Livewire::actingAs($this->makeAdmin())
                ->test($composant)
                ->viewData('staff')
                ->pluck('id');

            $this->assertTrue($propose->contains($caissier->getKey()), $composant);
        }
    }

    public function test_les_deux_formulaires_proposent_exactement_le_meme_personnel(): void
    {
        $service = Service::factory()->create();
        $this->makeDoctor($service);
        $this->makeReceptionist();
        $this->makeCashier();
        $this->makeNurse($service);

        $admin = $this->makeAdmin();

        $groupee = Livewire::actingAs($admin)->test(BulkScheduleForm::class)->viewData('staff')->pluck('id')->sort()->values();
        $jourParJour = Livewire::actingAs($admin)->test(ScheduleManager::class)->viewData('staff')->pluck('id')->sort()->values();

        // Deux listes differentes sur le meme ecran finissaient forcement par
        // diverger — c'est exactement ce qui s'etait produit.
        $this->assertEquals($groupee->all(), $jourParJour->all());
        $this->assertCount(4, $groupee);
    }

    public function test_un_compte_sans_rattachement_n_encombre_pas_les_menus(): void
    {
        // L'administrateur n'exerce dans aucun service : il n'a pas de garde,
        // donc rien a faire dans un menu de planning.
        $admin = $this->makeAdmin();

        $propose = Livewire::actingAs($admin)
            ->test(ScheduleManager::class)
            ->viewData('staff')
            ->pluck('id');

        $this->assertFalse($propose->contains($admin->getKey()));
    }

    public function test_l_infirmier_recoit_un_creneau_depuis_l_interface_d_administration(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $infirmier = $this->makeNurse($service);

        Livewire::actingAs($this->makeAdmin())
            ->test(ScheduleManager::class)
            ->set('user_id', $infirmier->getKey())
            ->set('date', today()->toDateString())
            ->set('start_time', '00:00')
            ->set('end_time', '23:59')
            ->set('service_id', $service->getKey())
            ->call('save')
            ->assertHasNoErrors();

        // Le but de tout ceci : il est enfin de garde, donc il voit ses soins.
        $this->assertTrue($infirmier->refresh()->isOnDutyFor($service->getKey()));
    }

    public function test_le_menu_nomme_le_type_du_personnel_sans_role(): void
    {
        $service = Service::factory()->create();
        $this->makeNurse($service);

        // Sans repli sur le type, la liste affichait « Bakary Coulibaly () » :
        // une parenthese vide, la ou le role manque.
        Livewire::actingAs($this->makeAdmin())
            ->test(ScheduleManager::class)
            ->assertSee('Bakary Coulibaly (Infirmier)')
            ->assertDontSee('Bakary Coulibaly ()');
    }

    public function test_la_liste_se_rafraichit_apres_une_generation_groupee(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);

        // Les deux formulaires sont deux composants voisins : sans evenement,
        // l'admin generait des creneaux et voyait un tableau vide.
        Livewire::actingAs($this->makeAdmin())
            ->test(BulkScheduleForm::class)
            ->set('user_id', $doctor->user_id)
            ->set('from', today()->toDateString())
            ->set('to', today()->addDays(6)->toDateString())
            ->set('weekdays', ['1', '2', '3', '4', '5', '6', '7'])
            ->set('start_time', '08:00')
            ->set('end_time', '14:00')
            ->call('generate')
            ->assertDispatched('plannings-mis-a-jour');

        $this->assertSame(7, Schedule::count());
    }

    // ------------------------------------------------ Suppression groupee

    public function test_la_suppression_groupee_efface_les_creneaux_coches(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);

        app(BulkCreateSchedule::class)->execute(
            user: $doctor->user,
            from: today(),
            to: today()->addDays(6),
            weekdays: [1, 2, 3, 4, 5, 6, 7],
            startTime: '08:00',
            endTime: '14:00',
            serviceId: $service->getKey(),
        );

        $this->assertSame(7, Schedule::count());

        $aEffacer = Schedule::orderBy('date')->limit(4)->pluck('id')->map(fn ($id) => (string) $id)->all();

        Livewire::actingAs($this->makeAdmin())
            ->test(ScheduleManager::class)
            ->set('selected', $aEffacer)
            ->call('deleteSelected');

        $this->assertSame(3, Schedule::count());

        $trace = Activity::where('event', Audit::EVENT_SCHEDULE_CHANGED)->latest('id')->firstOrFail();
        $this->assertStringContainsString('4 creneau(x) supprimes en une fois', $trace->description);
        $this->assertStringContainsString($doctor->user->name, $trace->description);
    }

    public function test_tout_selectionner_ne_porte_que_sur_ce_qui_est_affiche(): void
    {
        $service = Service::factory()->create();
        $premier = $this->makeDoctor($service);
        $second = $this->makeDoctor($service);

        foreach ([$premier, $second] as $medecin) {
            Schedule::create([
                'user_id' => $medecin->user_id,
                'service_id' => $service->getKey(),
                'date' => today()->toDateString(),
                'start_time' => '08:00:00',
                'end_time' => '14:00:00',
            ]);
        }

        // Filtre pose sur une seule personne : « tout selectionner » ne doit
        // pas emporter les creneaux de l'autre, qui ne sont pas a l'ecran.
        $composant = Livewire::actingAs($this->makeAdmin())
            ->test(ScheduleManager::class)
            ->set('filterUserId', $premier->user_id)
            ->call('toggleAll');

        $this->assertCount(1, $composant->get('selected'));

        $composant->call('deleteSelected');

        $this->assertSame(1, Schedule::count());
        $this->assertSame($second->user_id, Schedule::first()->user_id);
    }

    public function test_une_selection_devenue_invisible_n_est_pas_supprimee(): void
    {
        $service = Service::factory()->create();
        $premier = $this->makeDoctor($service);
        $second = $this->makeDoctor($service);

        $creneaux = collect([$premier, $second])->map(fn ($medecin) => Schedule::create([
            'user_id' => $medecin->user_id,
            'service_id' => $service->getKey(),
            'date' => today()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '14:00:00',
        ]));

        // On coche le creneau du second, puis on filtre sur le premier : la
        // selection gardee en memoire ne doit pas emporter ce qu'on ne voit
        // plus.
        Livewire::actingAs($this->makeAdmin())
            ->test(ScheduleManager::class)
            ->set('selected', [(string) $creneaux[1]->getKey()])
            ->set('filterUserId', $premier->user_id)
            ->call('deleteSelected');

        $this->assertSame(2, Schedule::count());
    }

    public function test_une_suppression_groupee_sans_selection_est_refusee_avec_sa_raison(): void
    {
        Livewire::actingAs($this->makeAdmin())
            ->test(ScheduleManager::class)
            ->call('deleteSelected')
            ->assertDispatched('message-affiche', level: 'error');
    }
}
