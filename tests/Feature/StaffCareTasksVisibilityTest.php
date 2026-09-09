<?php

namespace Tests\Feature;

use App\Actions\AdmitPatient;
use App\Actions\PrescribeCareTasks;
use App\Livewire\Admin\StaffManager;
use App\Models\CareTaskType;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\StaffMember;
use App\Models\StaffType;
use App\Models\User;
use App\Models\Visit;
use App\Services\SmsGateway;
use App\Services\SmsSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Visibilite des soins programmes cote infirmier (v3.2.3, point 1).
 *
 * La couverture du point 11 montait les composants directement. Elle ne
 * traversait donc pas la route `/staff/{slug}` : le rattachement au service,
 * la capacite du type et le rendu de la section n'etaient jamais eprouves
 * ensemble, alors que c'est precisement la ou une mauvaise configuration fait
 * disparaitre les soins sans rien dire.
 *
 * Ces tests partent de la prescription et vont jusqu'a l'ecran reel.
 */
class StaffCareTasksVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(SmsGateway::class)->shouldReceive('deliver')->andReturn(SmsSendResult::sent());
    }

    /**
     * Un infirmier rattache a un service, avec les capacites voulues.
     *
     * @param  array<int, string>  $capabilities
     */
    private function makeNurse(
        Service $service,
        array $capabilities = [StaffType::CAP_CARE_TASKS],
        bool $onDuty = true,
        string $name = 'Infirmier',
    ): User {
        $type = StaffType::create([
            'name' => $name,
            'matched_role' => null,
            'slug' => StaffType::makeSlug($name),
            'capabilities' => $capabilities,
        ]);

        $user = User::factory()->create();

        StaffMember::create([
            'user_id' => $user->getKey(),
            'staff_type_id' => $type->getKey(),
            'service_id' => $service->getKey(),
        ]);

        if ($onDuty) {
            Schedule::create([
                'user_id' => $user->getKey(),
                'service_id' => $service->getKey(),
                'date' => today()->toDateString(),
                'start_time' => '00:00:00',
                'end_time' => '23:59:00',
            ]);
        }

        return $user;
    }

    /** Prescrit un soin sur un service et renvoie le nom du patient. */
    private function prescrire(Service $service, string $soin = 'Serum'): string
    {
        $doctor = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        $hospitalisation = app(AdmitPatient::class)->execute($visit, $doctor);

        app(PrescribeCareTasks::class)->execute(
            hospitalization: $hospitalisation,
            type: CareTaskType::create(['name' => $soin]),
            doctor: $doctor,
            start: now()->addHour(),
            intervalHours: 12,
            durationDays: 1,
            instructions: 'Serum glucose 500 ml',
        );

        return $visit->patient->name;
    }

    public function test_un_soin_prescrit_apparait_sur_l_interface_de_l_infirmier(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $patient = $this->prescrire($service);
        $infirmier = $this->makeNurse($service);

        // Le scenario signale, de bout en bout : le medecin prescrit, et
        // l'infirmier de garde le voit sur sa propre interface, pas sur un
        // composant monte a la main dans un test.
        $this->actingAs($infirmier)
            ->get('/staff/infirmier')
            ->assertOk()
            ->assertSee('Soins programmes')
            ->assertSee('Serum')
            ->assertSee($patient)
            ->assertSee('Serum glucose 500 ml');
    }

    public function test_c_est_le_rattachement_au_service_qui_relie_le_soin_a_l_infirmier(): void
    {
        $medecine = Service::factory()->create(['name' => 'Medecine Generale']);
        $urgences = Service::factory()->create(['name' => 'Urgences']);

        $this->prescrire($medecine);

        // Meme type, meme garde, mais rattache ailleurs : staff_members.service_id
        // est le seul lien entre une personne et les soins qu'elle voit.
        $ailleurs = $this->makeNurse($urgences);

        $this->actingAs($ailleurs)
            ->get('/staff/infirmier')
            ->assertOk()
            ->assertDontSee('Serum');
    }

    public function test_un_type_sans_la_capacite_soins_n_a_pas_la_section(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $this->prescrire($service);

        // Premiere cause de « je ne vois pas les soins » : la capacite n'est
        // pas cochee sur le type. La section n'existe alors pas du tout.
        $agent = $this->makeNurse($service, capabilities: [StaffType::CAP_QUEUE]);

        $this->actingAs($agent)
            ->get('/staff/infirmier')
            ->assertOk()
            ->assertDontSee('Soins programmes')
            ->assertDontSee('Serum');
    }

    public function test_l_admin_est_averti_qu_une_personne_ne_verra_aucun_soin(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $this->seedRoles();

        // Un compte parfaitement valide, mais sans creneau : l'agent verra un
        // ecran vide, et l'administrateur n'avait jusqu'ici aucun moyen de
        // l'apprendre autrement que par une plainte.
        $this->makeNurse($service, onDuty: false);

        Livewire::actingAs($this->makeAdmin())
            ->test(StaffManager::class)
            ->assertSee('Aucun creneau de planning');
    }

    public function test_l_avertissement_disparait_des_qu_un_creneau_existe(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $this->seedRoles();

        $this->makeNurse($service, onDuty: true);

        Livewire::actingAs($this->makeAdmin())
            ->test(StaffManager::class)
            ->assertDontSee('Aucun creneau de planning');
    }

    public function test_un_type_sans_soins_ne_declenche_aucun_avertissement(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $this->seedRoles();

        // Le planning ne conditionne que les soins : avertir un agent qui n'en
        // execute pas serait un faux signal.
        $this->makeNurse($service, capabilities: [StaffType::CAP_QUEUE], onDuty: false);

        Livewire::actingAs($this->makeAdmin())
            ->test(StaffManager::class)
            ->assertDontSee('Aucun creneau de planning');
    }

    public function test_sans_creneau_de_planning_l_ecran_dit_pourquoi_la_liste_est_vide(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $this->prescrire($service);

        // Seconde cause : aucun creneau ne couvre l'heure. La liste est vide,
        // mais elle n'est pas muette : sinon l'infirmier croirait a une panne.
        $infirmier = $this->makeNurse($service, onDuty: false);

        $this->actingAs($infirmier)
            ->get('/staff/infirmier')
            ->assertOk()
            ->assertSee('Soins programmes')
            ->assertDontSee('Serum')
            ->assertSee("Vous n'etes pas de garde sur ce service en ce moment.", escape: false);
    }
}
