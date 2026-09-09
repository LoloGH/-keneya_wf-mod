<?php

namespace Tests\Feature;

use App\Actions\AdmitPatient;
use App\Actions\BulkCreateSchedule;
use App\Actions\CompleteReferral;
use App\Actions\PrescribeCareTasks;
use App\Actions\RegisterPatient;
use App\Actions\SendReferral;
use App\Livewire\Shared\NotificationBell;
use App\Models\Appointment;
use App\Models\CareTaskType;
use App\Models\Patient;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceKind;
use App\Models\Setting;
use App\Models\StaffMember;
use App\Models\StaffNotification;
use App\Models\StaffType;
use App\Models\User;
use App\Models\Visit;
use App\Models\Visitor;
use App\Services\SmsGateway;
use App\Services\SmsSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Notifications du personnel (v3.2.3, point 2).
 *
 * Le fil conducteur de ces tests : on ne previent que le personnel de garde
 * sur le service concerne. Une notification envoyee a tout l'hopital serait
 * ignoree en une journee, et la cloche ne vaudrait plus rien.
 */
class StaffNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ces tests decrivent une garde en journee : leur creneau va de
        // now()-1h a now()+3h. Entre minuit et une heure, ce calcul enjambe
        // deux dates — le creneau commence a 23:30 et finit a 03:30, ne
        // contient plus l'instant present, et personne n'est de garde. La
        // suite echouait alors une heure par nuit, sans que rien n'ait
        // change dans le code. L'horloge est donc posee a une heure ouvrable.
        $this->travelTo(today()->setTime(10, 0));

        $this->seedRoles();
        $this->mock(SmsGateway::class)->shouldReceive('deliver')->andReturn(SmsSendResult::sent());
    }

    /** Met une personne de garde sur un service, maintenant. */
    private function deGarde(User $user, Service $service): void
    {
        Schedule::create([
            'user_id' => $user->getKey(),
            'service_id' => $service->getKey(),
            'date' => today()->toDateString(),
            'start_time' => '00:00:00',
            'end_time' => '23:59:00',
        ]);
    }

    private function makeNurse(Service $service): User
    {
        $type = StaffType::create([
            'name' => 'Infirmier',
            'matched_role' => null,
            'slug' => StaffType::makeSlug('Infirmier'),
            'capabilities' => [StaffType::CAP_CARE_TASKS],
        ]);

        $user = User::factory()->create();

        StaffMember::create([
            'user_id' => $user->getKey(),
            'staff_type_id' => $type->getKey(),
            'service_id' => $service->getKey(),
        ]);

        return $user;
    }

    // ------------------------------------------- 1. Entree dans une file

    public function test_l_enregistrement_previent_le_personnel_de_garde_du_service(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $doctor = $this->makeDoctor($service);
        $this->deGarde($doctor->user, $service);

        app(RegisterPatient::class)->execute([
            'name' => 'Aminata Traore', 'age' => 34, 'gender' => 'Femme',
            'mobile' => '76000000', 'service_id' => $service->getKey(),
        ]);

        $notification = StaffNotification::where('user_id', $doctor->user_id)
            ->where('type', StaffNotification::TYPE_NEW_QUEUE_ENTRY)
            ->firstOrFail();

        $this->assertStringContainsString('Aminata Traore', $notification->title);
        $this->assertStringContainsString('Medecine Generale', $notification->title);
        $this->assertSame(route('service.home'), $notification->link);
    }

    public function test_le_personnel_hors_garde_n_est_pas_prevenu_quand_le_planning_est_tenu(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);

        $deGarde = $this->makeDoctor($service);
        $horsGarde = $this->makeDoctor($service);

        // La regle exacte : un planning tenu fait autorite. Tant qu'une
        // personne couvre le service, prevenir celles qui ne sont pas la
        // n'ajoute que du bruit a leur retour.
        //
        // Ce test affirmait auparavant la meme chose SANS aucun creneau, ce qui
        // revenait a ne prevenir personne du tout : c'est le silence complet
        // que cela produisait sur une installation au planning vide.
        Schedule::factory()->create([
            'user_id' => $deGarde->user_id,
            'service_id' => $service->getKey(),
            'date' => today(),
            'start_time' => now()->subHour()->format('H:i'),
            'end_time' => now()->addHours(3)->format('H:i'),
        ]);

        app(RegisterPatient::class)->execute([
            'name' => 'Aminata Traore', 'age' => 34, 'gender' => 'Femme',
            'mobile' => '76000000', 'service_id' => $service->getKey(),
        ]);

        $this->assertSame(0, StaffNotification::where('user_id', $horsGarde->user_id)->count());
        $this->assertGreaterThan(0, StaffNotification::where('user_id', $deGarde->user_id)->count());
    }

    public function test_le_personnel_d_un_autre_service_n_est_pas_prevenu(): void
    {
        $medecine = Service::factory()->create(['name' => 'Medecine Generale']);
        $urgences = Service::factory()->create(['name' => 'Urgences']);

        $ailleurs = $this->makeDoctor($urgences);
        $this->deGarde($ailleurs->user, $urgences);

        app(RegisterPatient::class)->execute([
            'name' => 'Aminata Traore', 'age' => 34, 'gender' => 'Femme',
            'mobile' => '76000000', 'service_id' => $medecine->getKey(),
        ]);

        $this->assertSame(0, StaffNotification::where('user_id', $ailleurs->user_id)->count());
    }

    public function test_un_visiteur_previent_aussi_la_file(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $doctor = $this->makeDoctor($service);
        $this->deGarde($doctor->user, $service);

        Visitor::create([
            'name' => 'Moussa Diallo',
            'service_id' => $service->getKey(),
            'token' => 1,
        ]);

        $this->assertSame(
            1,
            StaffNotification::where('user_id', $doctor->user_id)
                ->where('type', StaffNotification::TYPE_NEW_QUEUE_ENTRY)
                ->count(),
        );
    }

    public function test_un_changement_de_service_vaut_nouvelle_entree_en_file(): void
    {
        $medecine = Service::factory()->create(['name' => 'Medecine Generale']);
        $laboratoire = Service::factory()->ofKind($this->serviceKind(ServiceKind::SLUG_PLATEAU_TECHNIQUE))
            ->create(['name' => 'Laboratoire']);

        $prescripteur = $this->makeDoctor($medecine);
        $biologiste = $this->makeDoctor($laboratoire);
        $this->deGarde($biologiste->user, $laboratoire);

        $visit = $this->makeVisit($medecine, ['status' => Visit::STATUS_CALLED]);

        app(SendReferral::class)->execute($visit, $prescripteur, $laboratoire, 'Numeration.');

        // Le patient entre dans la file du laboratoire : le biologiste de garde
        // doit l'apprendre, quel que soit le chemin emprunte pour y arriver.
        $this->assertSame(
            1,
            StaffNotification::where('user_id', $biologiste->user_id)
                ->where('type', StaffNotification::TYPE_NEW_QUEUE_ENTRY)
                ->count(),
        );
    }

    // ------------------------------------------- 2. Resultat de renvoi

    public function test_le_resultat_d_un_renvoi_ne_previent_que_le_prescripteur(): void
    {
        $medecine = Service::factory()->create(['name' => 'Medecine Generale']);
        $laboratoire = Service::factory()->create(['name' => 'Laboratoire']);

        $prescripteur = $this->makeDoctor($medecine);
        $collegue = $this->makeDoctor($medecine);
        $this->deGarde($prescripteur->user, $medecine);
        $this->deGarde($collegue->user, $medecine);

        $biologiste = $this->makeDoctor($laboratoire);
        $visit = $this->makeVisit($medecine, ['status' => Visit::STATUS_CALLED]);

        $referral = app(SendReferral::class)->execute($visit, $prescripteur, $laboratoire, 'Numeration.');
        app(CompleteReferral::class)->execute($referral, $biologiste, 'Resultat normal.');

        $this->assertSame(
            1,
            StaffNotification::where('user_id', $prescripteur->user_id)
                ->where('type', StaffNotification::TYPE_REFERRAL_RESULT)
                ->count(),
        );

        // Le collegue est de garde sur le meme service, mais ce resultat ne
        // repond pas a sa question.
        $this->assertSame(
            0,
            StaffNotification::where('user_id', $collegue->user_id)
                ->where('type', StaffNotification::TYPE_REFERRAL_RESULT)
                ->count(),
        );
    }

    // ------------------------------------------- 3. Soins prescrits

    public function test_une_prescription_de_soins_previent_le_personnel_capable(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $doctor = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);
        $hospitalisation = app(AdmitPatient::class)->execute($visit, $doctor);

        $infirmier = $this->makeNurse($service);
        $this->deGarde($infirmier, $service);

        // De garde sur le meme service, mais son type ne porte pas la capacite
        // « soins » : il n'a meme pas la section pour les voir.
        $brancardier = User::factory()->create();
        $typeSansSoins = StaffType::create([
            'name' => 'Brancardier', 'matched_role' => null,
            'slug' => 'brancardier', 'capabilities' => [StaffType::CAP_QUEUE],
        ]);
        StaffMember::create([
            'user_id' => $brancardier->getKey(),
            'staff_type_id' => $typeSansSoins->getKey(),
            'service_id' => $service->getKey(),
        ]);
        $this->deGarde($brancardier, $service);

        app(PrescribeCareTasks::class)->execute(
            hospitalization: $hospitalisation,
            type: CareTaskType::create(['name' => 'Serum']),
            doctor: $doctor,
            start: now()->addHour(),
            intervalHours: 12,
            durationDays: 1,
        );

        $notification = StaffNotification::where('user_id', $infirmier->getKey())
            ->where('type', StaffNotification::TYPE_CARE_TASK_ASSIGNED)
            ->firstOrFail();

        $this->assertStringContainsString('2 soin(s)', $notification->title);
        $this->assertSame(0, StaffNotification::where('user_id', $brancardier->getKey())
            ->where('type', StaffNotification::TYPE_CARE_TASK_ASSIGNED)->count());
    }

    // ------------------------------------------- 4. Rappel de rendez-vous

    public function test_le_rappel_part_dans_la_fenetre_configuree_et_une_seule_fois(): void
    {
        Setting::put(Setting::APPOINTMENT_REMINDER_MINUTES, '60');

        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $doctor = $this->makeDoctor($service);
        $patient = Patient::factory()->create(['name' => 'Aminata Traore']);

        $proche = Appointment::create([
            'patient_id' => $patient->getKey(),
            'doctor_id' => $doctor->getKey(),
            'service_id' => $service->getKey(),
            'scheduled_at' => now()->addMinutes(30),
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        // Hors fenetre : demain, il n'a pas a sonner aujourd'hui.
        Appointment::create([
            'patient_id' => $patient->getKey(),
            'doctor_id' => $doctor->getKey(),
            'service_id' => $service->getKey(),
            'scheduled_at' => now()->addDay(),
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        $this->artisan('keneya:rappels-rendez-vous')->assertSuccessful();

        $rappels = StaffNotification::where('user_id', $doctor->user_id)
            ->where('type', StaffNotification::TYPE_APPOINTMENT_REMINDER);

        $this->assertSame(1, $rappels->count());
        $this->assertNotNull($proche->refresh()->reminder_sent_at);

        // Deuxieme passage : la commande repasse toutes les quinze minutes sur
        // une fenetre qui se recouvre, elle ne doit pas sonner deux fois.
        $this->artisan('keneya:rappels-rendez-vous')->assertSuccessful();

        $this->assertSame(1, $rappels->count());
    }

    public function test_le_delai_du_rappel_se_regle_sans_toucher_au_code(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $doctor = $this->makeDoctor($service);
        $patient = Patient::factory()->create(['name' => 'Aminata Traore']);

        Appointment::create([
            'patient_id' => $patient->getKey(),
            'doctor_id' => $doctor->getKey(),
            'service_id' => $service->getKey(),
            'scheduled_at' => now()->addMinutes(90),
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        Setting::put(Setting::APPOINTMENT_REMINDER_MINUTES, '30');
        $this->artisan('keneya:rappels-rendez-vous')->assertSuccessful();
        $this->assertSame(0, StaffNotification::where('type', StaffNotification::TYPE_APPOINTMENT_REMINDER)->count());

        Setting::put(Setting::APPOINTMENT_REMINDER_MINUTES, '120');
        $this->artisan('keneya:rappels-rendez-vous')->assertSuccessful();
        $this->assertSame(1, StaffNotification::where('type', StaffNotification::TYPE_APPOINTMENT_REMINDER)->count());
    }

    // ------------------------------------------- 5. Planning publie

    public function test_la_publication_d_un_planning_previent_les_personnes_concernees(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $doctor = $this->makeDoctor($service);

        app(BulkCreateSchedule::class)->execute(
            user: $doctor->user,
            from: Carbon::parse('2027-03-01'),
            to: Carbon::parse('2027-03-07'),
            weekdays: [1, 2, 3, 4, 5],
            startTime: '08:00',
            endTime: '14:00',
            serviceId: $service->getKey(),
        );

        $notification = StaffNotification::where('user_id', $doctor->user_id)
            ->where('type', StaffNotification::TYPE_SCHEDULE_PUBLISHED)
            ->firstOrFail();

        // Une notification pour la publication, pas une par creneau : cinq
        // lignes de planning ne font pas cinq nouvelles.
        $this->assertSame(1, StaffNotification::where('type', StaffNotification::TYPE_SCHEDULE_PUBLISHED)->count());
        $this->assertStringContainsString('5 creneau(x)', $notification->title);
        $this->assertStringContainsString('01/03/2027', $notification->title);
    }

    // ------------------------------------------- La cloche

    public function test_la_cloche_compte_les_non_lues_et_les_marque_a_l_ouverture(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);

        StaffNotification::insert([
            ['user_id' => $doctor->user_id, 'type' => StaffNotification::TYPE_NEW_QUEUE_ENTRY,
                'title' => 'Un patient entre dans la file.', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $doctor->user_id, 'type' => StaffNotification::TYPE_REFERRAL_RESULT,
                'title' => 'Resultat recu.', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $cloche = Livewire::actingAs($doctor->user)->test(NotificationBell::class);

        $cloche->assertSee('2');

        $cloche->call('toggle')
            ->assertSee('Un patient entre dans la file.')
            ->assertSee('Resultat recu.');

        $this->assertSame(0, StaffNotification::where('user_id', $doctor->user_id)->unread()->count());
    }

    public function test_le_son_ne_sonne_qu_a_l_arrivee_d_une_nouvelle_notification(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);

        $cloche = Livewire::actingAs($doctor->user)->test(NotificationBell::class);

        // Un sondage sans rien de neuf ne sonne pas — sinon la cloche
        // retentirait toutes les dix secondes.
        $cloche->call('refresh')->assertNotDispatched('notification-nouvelle');

        StaffNotification::create([
            'user_id' => $doctor->user_id,
            'type' => StaffNotification::TYPE_NEW_QUEUE_ENTRY,
            'title' => 'Un patient entre dans la file.',
        ]);

        $cloche->call('refresh')->assertDispatched('notification-nouvelle');

        // Le meme total au sondage suivant : toujours pas de son.
        $cloche->call('refresh')->assertNotDispatched('notification-nouvelle');
    }

    public function test_la_cloche_d_un_compte_ne_montre_pas_les_notifications_d_un_autre(): void
    {
        $service = Service::factory()->create();
        $mien = $this->makeDoctor($service);
        $autre = $this->makeDoctor($service);

        StaffNotification::create([
            'user_id' => $autre->user_id,
            'type' => StaffNotification::TYPE_NEW_QUEUE_ENTRY,
            'title' => 'Secret professionnel.',
        ]);

        Livewire::actingAs($mien->user)
            ->test(NotificationBell::class)
            ->call('toggle')
            ->assertDontSee('Secret professionnel.');
    }
}
