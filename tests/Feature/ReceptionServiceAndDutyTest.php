<?php

namespace Tests\Feature;

use App\Actions\RegisterPatient;
use App\Livewire\Admin\BulkScheduleForm;
use App\Livewire\Admin\ServiceManager;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceKind;
use App\Models\StaffMember;
use App\Models\StaffNotification;
use App\Models\StaffType;
use App\Models\User;
use App\Services\OnDutyRoster;
use App\Services\SmsGateway;
use App\Services\SmsSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L'accueil comme service, et le creneau sans service (v3.2.5).
 *
 * Deux symptomes d'une meme cause : une receptionniste n'avait aucun service a
 * designer sur son creneau, et un creneau muet ne rendait de garde pour rien,
 * donc aucune notification ne partait.
 */
class ReceptionServiceAndDutyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->mock(SmsGateway::class)->shouldReceive('deliver')->andReturn(SmsSendResult::sent());
    }

    private function reception(): Service
    {
        $kind = ServiceKind::firstOrCreate(
            ['slug' => ServiceKind::SLUG_RECEPTION],
            ['name' => 'Accueil', 'requires_payment_gate' => false],
        );

        return Service::firstOrCreate(
            ['name' => Service::RECEPTION],
            ['service_kind_id' => $kind->getKey()],
        );
    }

    private function deGarde(User $user, ?Service $service): Schedule
    {
        return Schedule::create([
            'user_id' => $user->getKey(),
            'service_id' => $service?->getKey(),
            'date' => today()->toDateString(),
            'start_time' => '00:00:00',
            'end_time' => '23:59:00',
        ]);
    }

    // -------------------------------------------------- L'accueil est un service

    public function test_l_accueil_figure_dans_la_liste_des_services(): void
    {
        $this->reception();

        Livewire::actingAs($this->makeAdmin())
            ->test(ServiceManager::class)
            ->assertSee('Accueil');
    }

    public function test_l_accueil_est_proposable_dans_le_menu_service_des_plannings(): void
    {
        $accueil = $this->reception();

        $propose = Livewire::actingAs($this->makeAdmin())
            ->test(BulkScheduleForm::class)
            ->viewData('services')
            ->pluck('id');

        $this->assertTrue($propose->contains($accueil->getKey()));
    }

    public function test_l_accueil_n_est_pas_une_destination_de_soins(): void
    {
        $this->reception();
        Service::factory()->create(['name' => 'Medecine Generale']);

        // On n'oriente pas un patient « vers l'accueil » : c'est une etape du
        // parcours, pas un lieu de consultation.
        $destinations = Service::careServices()->pluck('name');

        $this->assertFalse($destinations->contains(Service::RECEPTION));
        $this->assertTrue($destinations->contains('Medecine Generale'));
    }

    public function test_la_receptionniste_est_de_garde_a_l_accueil(): void
    {
        $accueil = $this->reception();
        $receptionniste = $this->makeReceptionist();

        // Avant, elle n'avait aucun service a designer : elle n'etait de garde
        // nulle part, et ne recevait donc jamais rien.
        $this->deGarde($receptionniste, $accueil);

        $this->assertTrue($receptionniste->isOnDutyFor($accueil->getKey()));

        $deGarde = app(OnDutyRoster::class)->for($accueil->getKey())->pluck('id');
        $this->assertTrue($deGarde->contains($receptionniste->getKey()));
    }

    // ------------------------------------------------ Le creneau sans service

    public function test_un_creneau_sans_service_vaut_pour_le_service_de_rattachement(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $doctor = $this->makeDoctor($service);

        // « 08h a 14h, aucun service » : la seule lecture raisonnable est
        // « a son service ». Avant, ce creneau ne servait a rien.
        $this->deGarde($doctor->user, null);

        $this->assertTrue($doctor->user->isOnDutyFor($service->getKey()));
    }

    public function test_un_creneau_sans_service_ne_deborde_pas_sur_les_autres_services(): void
    {
        $medecine = Service::factory()->create(['name' => 'Medecine Generale']);
        $urgences = Service::factory()->create(['name' => 'Urgences']);
        $doctor = $this->makeDoctor($medecine);

        $this->deGarde($doctor->user, null);

        // Un creneau muet ne rend pas de garde partout : seulement la ou la
        // personne exerce.
        $this->assertFalse($doctor->user->isOnDutyFor($urgences->getKey()));
    }

    public function test_un_creneau_sans_service_couvre_aussi_le_personnel_generique(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);

        $type = StaffType::create([
            'name' => 'Infirmier', 'matched_role' => null,
            'slug' => 'infirmier', 'capabilities' => [StaffType::CAP_CARE_TASKS],
        ]);
        $infirmier = User::factory()->create();
        StaffMember::create([
            'user_id' => $infirmier->getKey(),
            'staff_type_id' => $type->getKey(),
            'service_id' => $service->getKey(),
        ]);

        $this->deGarde($infirmier, null);

        $this->assertTrue($infirmier->isOnDutyFor($service->getKey()));
    }

    public function test_un_creneau_muet_ne_suffit_pas_a_qui_n_a_pas_de_rattachement(): void
    {
        $accueil = $this->reception();
        $receptionniste = $this->makeReceptionist();

        // Une receptionniste n'a pas de colonne de service : son creneau doit
        // nommer l'accueil, sinon il ne designe rien.
        $this->deGarde($receptionniste, null);

        $this->assertFalse($receptionniste->isOnDutyFor($accueil->getKey()));
    }

    // -------------------------------------- La notification part enfin

    public function test_un_creneau_sans_service_suffit_a_declencher_la_notification(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $doctor = $this->makeDoctor($service);

        $this->deGarde($doctor->user, null);

        app(RegisterPatient::class)->execute([
            'name' => 'Aminata Traore', 'age' => 34, 'gender' => 'Femme',
            'mobile' => '76000000', 'service_id' => $service->getKey(),
        ]);

        // C'est le defaut signale : les plannings etaient poses sans service,
        // et plus aucune notification ne partait.
        $notification = StaffNotification::where('user_id', $doctor->user_id)
            ->where('type', StaffNotification::TYPE_NEW_QUEUE_ENTRY)
            ->firstOrFail();

        $this->assertStringContainsString('Aminata Traore', $notification->title);
    }

    public function test_la_migration_pose_l_accueil_une_seule_fois(): void
    {
        // La migration a deja tourne. La rejouer ne doit rien dupliquer : une
        // installation qui aurait cree son accueil a la main garde le sien.
        $migration = require database_path('migrations/2025_08_01_000100_add_reception_service_kind.php');
        $migration->up();

        $this->assertSame(1, Service::where('name', Service::RECEPTION)->count());
        $this->assertSame(1, ServiceKind::where('slug', ServiceKind::SLUG_RECEPTION)->count());
    }
}
