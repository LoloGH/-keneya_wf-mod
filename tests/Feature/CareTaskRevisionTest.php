<?php

namespace Tests\Feature;

use App\Actions\AdmitPatient;
use App\Actions\CompleteCareTask;
use App\Actions\DischargePatient;
use App\Actions\PrescribeCareTasks;
use App\Actions\ReviseCareTask;
use App\Livewire\Service\Hospitalizations;
use App\Livewire\Staff\StaffCareTasks;
use App\Models\CareTask;
use App\Models\CareTaskType;
use App\Models\Doctor;
use App\Models\Hospitalization;
use App\Models\PatientHistory;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\StaffMember;
use App\Models\StaffType;
use App\Models\User;
use App\Models\Visit;
use App\Services\SmsGateway;
use App\Services\SmsSendResult;
use App\Support\Audit;
use App\Support\Roles;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Correction et annulation d'un soin administre (v3.2.3, point 4).
 *
 * Le fil conducteur : rien ne s'efface. Un soin corrige garde son avant et son
 * apres au journal ; un soin annule reste au dossier mais sort de tous les
 * comptes.
 */
class CareTaskRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(SmsGateway::class)->shouldReceive('deliver')->andReturn(SmsSendResult::sent());
    }

    /** @return array{0: Service, 1: Doctor, 2: Hospitalization, 3: CareTask} */
    private function makeSoin(int $occurrences = 1): array
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $doctor = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        $hospitalisation = app(AdmitPatient::class)->execute($visit, $doctor);

        app(PrescribeCareTasks::class)->execute(
            hospitalization: $hospitalisation,
            type: CareTaskType::create(['name' => 'Serum']),
            doctor: $doctor,
            start: Carbon::parse('2027-03-01 08:00'),
            intervalHours: 24,
            durationDays: $occurrences,
            instructions: 'Serum glucose 500 ml',
        );

        return [
            $service,
            $doctor,
            $hospitalisation->refresh(),
            CareTask::orderBy('scheduled_at')->firstOrFail(),
        ];
    }

    /** Un infirmier de garde sur le service, avec la capacite « soins ». */
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

        Schedule::create([
            'user_id' => $user->getKey(),
            'service_id' => $service->getKey(),
            'date' => today()->toDateString(),
            'start_time' => '00:00:00',
            'end_time' => '23:59:00',
        ]);

        return $user;
    }

    // ------------------------------------------------------------ Correction

    public function test_le_prescripteur_corrige_un_soin_et_le_journal_garde_l_avant_et_l_apres(): void
    {
        [, $doctor, , $task] = $this->makeSoin();

        app(ReviseCareTask::class)->revise($task, $doctor->user, [
            'instructions' => 'Serum glucose 250 ml',
            'scheduled_at' => '2027-03-01 10:00',
        ]);

        $task->refresh();

        $this->assertSame('Serum glucose 250 ml', $task->instructions);
        $this->assertSame('2027-03-01 10:00', $task->scheduled_at->format('Y-m-d H:i'));

        $trace = Activity::where('event', Audit::EVENT_CARE_TASK_REVISED)->latest('id')->firstOrFail();

        // Une correction qui ne dirait pas ce qui a change ne vaudrait guere
        // mieux qu'une modification silencieuse.
        $this->assertStringContainsString('Serum glucose 500 ml', $trace->description);
        $this->assertStringContainsString('Serum glucose 250 ml', $trace->description);
        $this->assertStringContainsString('01/03/2027 08:00', $trace->description);
        $this->assertStringContainsString('01/03/2027 10:00', $trace->description);
    }

    public function test_la_correction_laisse_une_ligne_au_dossier_du_patient(): void
    {
        [, $doctor, , $task] = $this->makeSoin();

        app(ReviseCareTask::class)->revise($task, $doctor->user, [
            'instructions' => 'Serum glucose 250 ml',
        ]);

        $ligne = PatientHistory::where('type', PatientHistory::TYPE_CARE_TASK_REVISED)->latest('id')->first();

        $this->assertNotNull($ligne, 'La correction doit apparaitre au dossier du patient.');
        $this->assertSame($doctor->getKey(), $ligne->doctor_id);
    }

    public function test_une_correction_sans_changement_ne_laisse_pas_de_trace(): void
    {
        [, $doctor, , $task] = $this->makeSoin();

        app(ReviseCareTask::class)->revise($task, $doctor->user, [
            'instructions' => 'Serum glucose 500 ml',
        ]);

        // Une ligne d'audit pour une correction qui n'a pas eu lieu rendrait le
        // journal illisible.
        $this->assertSame(0, Activity::where('event', Audit::EVENT_CARE_TASK_REVISED)->count());
    }

    // ------------------------------------------------------------ Annulation

    public function test_l_annulation_exige_un_motif(): void
    {
        [, $doctor, , $task] = $this->makeSoin();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Indiquez le motif de l'annulation.");

        app(ReviseCareTask::class)->cancel($task, $doctor->user, '   ');
    }

    public function test_un_soin_annule_reste_en_base_avec_son_motif_et_son_auteur(): void
    {
        [, $doctor, , $task] = $this->makeSoin();

        app(ReviseCareTask::class)->cancel($task, $doctor->user, 'Erreur de saisie');

        $task->refresh();

        // Pas de suppression silencieuse : la ligne demeure.
        $this->assertDatabaseHas('care_tasks', ['id' => $task->getKey()]);
        $this->assertSame(CareTask::STATUS_CANCELLED, $task->status);
        $this->assertSame('Erreur de saisie', $task->cancellation_reason);
        $this->assertSame($doctor->user_id, $task->cancelled_by_user_id);
        $this->assertNotNull($task->cancelled_at);
        $this->assertSame('Annule', $task->statusLabel());

        $trace = Activity::where('event', Audit::EVENT_CARE_TASK_CANCELLED)->latest('id')->firstOrFail();
        $this->assertStringContainsString('Erreur de saisie', $trace->description);
        $this->assertStringContainsString($doctor->user->name, $trace->description);

        $this->assertSame(
            1,
            PatientHistory::where('type', PatientHistory::TYPE_CARE_TASK_CANCELLED)->count(),
        );
    }

    public function test_un_soin_deja_administre_peut_etre_annule(): void
    {
        [$service, $doctor, , $task] = $this->makeSoin();
        $nurse = $this->makeNurse($service);

        app(CompleteCareTask::class)->execute($task, $nurse);

        $this->assertTrue($task->refresh()->isDone());

        app(ReviseCareTask::class)->cancel($task, $doctor->user, 'Administration notee par erreur');

        // Sans cela, une saisie fautive resterait comptee comme un soin
        // reellement administre.
        $this->assertTrue($task->refresh()->isCancelled());
        $this->assertSame(0, CareTask::countable()->where('status', CareTask::STATUS_DONE)->count());

        $trace = Activity::where('event', Audit::EVENT_CARE_TASK_CANCELLED)->latest('id')->firstOrFail();
        $this->assertStringContainsString('etait : Fait', $trace->description);
    }

    public function test_un_soin_annule_ne_peut_plus_etre_modifie_ni_reannule(): void
    {
        [, $doctor, , $task] = $this->makeSoin();

        app(ReviseCareTask::class)->cancel($task, $doctor->user, 'Prescription arretee');

        try {
            app(ReviseCareTask::class)->cancel($task->refresh(), $doctor->user, 'Encore');
            $this->fail('La seconde annulation devait etre refusee.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('deja annule', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        app(ReviseCareTask::class)->revise($task->refresh(), $doctor->user, ['instructions' => 'Autre chose']);
    }

    // ------------------------------------------------------------- Decomptes

    public function test_un_soin_annule_ne_compte_plus_dans_les_totaux(): void
    {
        [$service, $doctor, $hospitalisation, $task] = $this->makeSoin(occurrences: 3);

        $this->assertSame(3, $hospitalisation->pendingCareTasks());

        app(ReviseCareTask::class)->cancel($task, $doctor->user, 'Prescription arretee');

        $this->assertSame(2, $hospitalisation->refresh()->pendingCareTasks());

        // Le compteur affiche au medecin suit la meme regle.
        $affiche = Livewire::actingAs($doctor->user)
            ->test(Hospitalizations::class, ['serviceId' => $service->getKey()])
            ->viewData('hospitalizations')
            ->firstOrFail();

        $this->assertSame(2, $affiche->pending_care_tasks_count);
    }

    public function test_un_soin_annule_ne_bloque_plus_la_sortie_d_hospitalisation(): void
    {
        [, $doctor, $hospitalisation, $task] = $this->makeSoin();

        try {
            app(DischargePatient::class)->execute($hospitalisation, $doctor);
            $this->fail('Un soin en attente devait bloquer la sortie.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('soin', $e->getMessage());
        }

        app(ReviseCareTask::class)->cancel($task, $doctor->user, 'Prescription arretee');

        app(DischargePatient::class)->execute($hospitalisation->refresh(), $doctor);

        $this->assertSame(Hospitalization::STATUS_DISCHARGED, $hospitalisation->refresh()->status);
    }

    public function test_un_soin_annule_disparait_de_la_feuille_de_garde(): void
    {
        [$service, $doctor, , $task] = $this->makeSoin();
        $nurse = $this->makeNurse($service);

        Livewire::actingAs($nurse)
            ->test(StaffCareTasks::class)
            ->set('showDone', true)
            ->assertSee('Serum');

        app(ReviseCareTask::class)->cancel($task, $doctor->user, 'Prescription arretee');

        $restants = Livewire::actingAs($nurse)
            ->test(StaffCareTasks::class)
            ->set('showDone', true)
            ->viewData('tasks');

        $this->assertTrue($restants->isEmpty(), 'Un soin annule n\'a plus rien a faire sur la feuille de garde.');
    }

    public function test_un_soin_annule_ne_peut_plus_etre_marque_fait(): void
    {
        [$service, $doctor, , $task] = $this->makeSoin();
        $nurse = $this->makeNurse($service);

        app(ReviseCareTask::class)->cancel($task, $doctor->user, 'Prescription arretee');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ce soin a ete annule');

        app(CompleteCareTask::class)->execute($task->refresh(), $nurse);
    }

    // -------------------------------------------------------- Controle d'acces

    public function test_un_medecin_d_un_autre_service_ne_touche_pas_au_soin(): void
    {
        [, , , $task] = $this->makeSoin();
        $ailleurs = $this->makeDoctor(Service::factory()->create(['name' => 'Urgences']));

        try {
            app(ReviseCareTask::class)->cancel($task, $ailleurs->user, 'Pas mon patient');
            $this->fail("L'annulation devait etre refusee.");
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Seul le prescripteur', $e->getMessage());
        }

        $this->assertSame(CareTask::STATUS_PENDING, $task->refresh()->status);
    }

    public function test_un_medecin_du_service_en_charge_des_hospitalisations_le_peut(): void
    {
        [$service, , , $task] = $this->makeSoin();
        $collegue = $this->makeDoctor($service);

        // Le prescripteur n'est pas de garde tous les jours : le service doit
        // pouvoir corriger sans attendre son retour.
        app(ReviseCareTask::class)->cancel($task, $collegue->user, 'Etat du patient change');

        $this->assertTrue($task->refresh()->isCancelled());
    }

    public function test_l_infirmier_de_garde_n_annule_pas_une_prescription(): void
    {
        [$service, , , $task] = $this->makeSoin();
        $nurse = $this->makeNurse($service);

        // Executer un soin et decider de son arret sont deux choses
        // differentes : marquer « manque » reste ouvert, annuler non.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Seul le prescripteur');

        app(ReviseCareTask::class)->cancel($task, $nurse, 'Je prefere pas');
    }

    public function test_les_soins_d_une_hospitalisation_cloturee_ne_bougent_plus(): void
    {
        [, $doctor, $hospitalisation, $task] = $this->makeSoin();

        app(ReviseCareTask::class)->cancel($task, $doctor->user, 'Prescription arretee');
        app(DischargePatient::class)->execute($hospitalisation->refresh(), $doctor);

        $autre = CareTask::create([
            'hospitalization_id' => $hospitalisation->getKey(),
            'care_task_type_id' => CareTaskType::first()->getKey(),
            'prescribed_by_doctor_id' => $doctor->getKey(),
            'scheduled_at' => now(),
            'status' => CareTask::STATUS_PENDING,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cloturee');

        app(ReviseCareTask::class)->cancel($autre, $doctor->user, 'Trop tard');
    }

    // ------------------------------------------------------ Interface medecin

    public function test_le_medecin_corrige_et_annule_depuis_l_interface_service(): void
    {
        [$service, $doctor, , $task] = $this->makeSoin(occurrences: 2);

        $composant = Livewire::actingAs($doctor->user)
            ->test(Hospitalizations::class, ['serviceId' => $service->getKey()])
            ->call('showCareTasks', $task->hospitalization_id)
            ->assertSee('Serum glucose 500 ml');

        $composant
            ->call('startRevision', $task->getKey())
            ->set('reviseInstructions', 'Serum glucose 250 ml')
            ->call('saveRevision')
            ->assertHasNoErrors();

        $this->assertSame('Serum glucose 250 ml', $task->refresh()->instructions);

        // Le motif n'est pas facultatif, meme depuis l'ecran.
        $composant
            ->call('startCancellation', $task->getKey())
            ->set('cancellationReason', '')
            ->call('confirmCancellation')
            ->assertHasErrors('cancellationReason');

        $composant
            ->set('cancellationReason', 'Erreur de saisie')
            ->call('confirmCancellation')
            ->assertHasNoErrors();

        $this->assertTrue($task->refresh()->isCancelled());
    }

    public function test_le_refus_est_affiche_au_medecin_et_non_avale(): void
    {
        [$service, , , $task] = $this->makeSoin();

        // Un autre medecin du service, sans la capacite d'hospitalisation :
        // il n'a rien a decider de cette prescription.
        $collegue = $this->makeDoctor($service);
        $type = StaffType::create([
            'name' => 'Medecin consultant',
            'matched_role' => Roles::DOCTOR,
            'slug' => null,
            'capabilities' => [],
        ]);
        $collegue->user->update(['staff_type_id' => $type->getKey()]);

        $this->assertFalse($collegue->user->refresh()->hasCapability(StaffType::CAP_ADMIT_HOSPITALIZATION));

        // La section entiere lui est fermee : le refus se lit des le montage,
        // il ne clique pas dans le vide.
        Livewire::actingAs($collegue->user)
            ->test(Hospitalizations::class, ['serviceId' => $service->getKey()])
            ->assertStatus(403);

        $this->assertSame(CareTask::STATUS_PENDING, $task->refresh()->status);
    }

    public function test_l_echec_d_une_annulation_pousse_le_message_au_bandeau(): void
    {
        [$service, $doctor, , $task] = $this->makeSoin();

        app(ReviseCareTask::class)->cancel($task, $doctor->user, 'Prescription arretee');

        // Le bandeau est rendu par Livewire : sans l'evenement, un refus
        // n'apparaitrait qu'au rechargement suivant, donc jamais.
        Livewire::actingAs($doctor->user)
            ->test(Hospitalizations::class, ['serviceId' => $service->getKey()])
            ->call('showCareTasks', $task->hospitalization_id)
            ->call('startCancellation', $task->getKey())
            ->set('cancellationReason', 'Encore')
            ->call('confirmCancellation')
            ->assertDispatched('message-affiche', level: 'error');
    }

    public function test_le_medecin_ne_voit_pas_les_soins_d_un_autre_service(): void
    {
        [, , , $task] = $this->makeSoin();
        $ailleurs = Service::factory()->create(['name' => 'Urgences']);
        $autreMedecin = $this->makeDoctor($ailleurs);

        // Le cloisonnement precede le controle d'acces de l'action : un soin
        // d'un autre service n'existe pas de ce point de vue.
        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($autreMedecin->user)
            ->test(Hospitalizations::class, ['serviceId' => $ailleurs->getKey()])
            ->call('startCancellation', $task->getKey());
    }
}
