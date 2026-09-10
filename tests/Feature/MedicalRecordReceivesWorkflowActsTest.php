<?php

namespace Tests\Feature;

use App\Actions\AdmitPatient;
use App\Actions\CheckInAppointment;
use App\Actions\CompleteCareTask;
use App\Actions\DischargePatient;
use App\Actions\PrescribeCareTasks;
use App\Actions\ScheduleAppointment;
use App\Models\CareTask;
use App\Models\CareTaskType;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\StaffMember;
use App\Models\StaffType;
use App\Models\User;
use App\Models\Visit;
use App\Services\SmsGateway;
use App\Services\SmsSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Keneya\Dme\Models\Appointment as RendezVousDme;
use Keneya\Dme\Models\CareOrder;
use Keneya\Dme\Models\Hospitalization as HospitalisationDme;
use Tests\TestCase;

/**
 * Les trois actes de WorkFlow qui n'atteignaient pas le dossier (v3.3.2).
 *
 * Fixer un rendez-vous, hospitaliser, prescrire des soins : trois gestes
 * quotidiens, trois tables dans le module, trois onglets dans le dossier du
 * patient — et rien ne les reliait. Les ordonnances, les consultations et les
 * examens avaient recu leur action de pont pendant la v3.3.1 ; ces trois-la
 * ont ete oubliees, et le defaut ne se voyait que du cote DME, sur des onglets
 * vides qu'on pouvait croire simplement inutilises.
 *
 * Comptage a la decouverte : deux rendez-vous, une hospitalisation et un soin
 * dans WorkFlow, zero de chaque au dossier, contre deux ordonnances et deux
 * consultations bien projetees.
 */
class MedicalRecordReceivesWorkflowActsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->mock(SmsGateway::class)->shouldReceive('deliver')->andReturn(SmsSendResult::sent());

        // L'horloge est fixee : les creneaux de garde de ces tests couvrent la
        // journee courante, et une execution a minuit passerait d'un jour a
        // l'autre entre la pose du creneau et le marquage du soin.
        $this->travelTo(today()->setTime(10, 0));
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

    // ------------------------------------------------------- Les rendez-vous

    public function test_le_rendez_vous_fixe_atterrit_au_dossier(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine generale']);
        $medecin = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        $quand = now()->addWeek()->setTime(9, 30);

        $rendezVous = app(ScheduleAppointment::class)->execute($visit, $medecin, $quand);

        $this->assertNotNull($rendezVous->dme_appointment_id, 'Le rendez-vous doit renvoyer a sa ligne du dossier.');

        $auDossier = RendezVousDme::findOrFail($rendezVous->dme_appointment_id);

        $this->assertSame('scheduled', $auDossier->status);
        $this->assertSame($quand->format('Y-m-d H:i'), $auDossier->scheduled_for->format('Y-m-d H:i'));
        $this->assertSame($medecin->user_id, $auDossier->doctor_id);
    }

    /**
     * L'accueil marque l'arrivee, l'absence ou l'annulation : le dossier doit
     * suivre, sinon il annoncerait un rendez-vous a venir pour un patient qui
     * n'est jamais venu.
     */
    public function test_le_statut_du_rendez_vous_suit_les_gestes_de_l_accueil(): void
    {
        $service = Service::factory()->create();
        $medecin = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        $rendezVous = app(ScheduleAppointment::class)->execute($visit, $medecin, now()->addWeek());

        app(CheckInAppointment::class)->markNoShow($rendezVous);

        $this->assertSame('no_show', RendezVousDme::findOrFail($rendezVous->dme_appointment_id)->status);
    }

    // -------------------------------------------------- L'hospitalisation

    public function test_l_hospitalisation_apparait_au_dossier_des_l_admission(): void
    {
        $service = Service::factory()->create(['name' => 'Chirurgie']);
        $medecin = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);
        $salle = Room::create(['service_id' => $service->getKey(), 'name' => 'Salle 3', 'capacity' => 4]);

        $sejour = app(AdmitPatient::class)->execute($visit, $medecin, $salle);

        $this->assertNotNull($sejour->dme_hospitalization_id);

        $auDossier = HospitalisationDme::findOrFail($sejour->dme_hospitalization_id);

        $this->assertSame('admitted', $auDossier->status);
        $this->assertSame('Salle 3', $auDossier->room);
        $this->assertSame($medecin->user_id, $auDossier->doctor_id);
        $this->assertNull($auDossier->discharged_at);
    }

    /**
     * La verification qui compte : la sortie ferme le sejour existant, elle
     * n'en ouvre pas un second.
     */
    public function test_la_sortie_ferme_le_sejour_du_dossier_sans_en_creer_un_autre(): void
    {
        $service = Service::factory()->create();
        $medecin = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        $sejour = app(AdmitPatient::class)->execute($visit, $medecin);

        app(DischargePatient::class)->execute($sejour, $medecin);

        $this->assertSame(1, HospitalisationDme::count(), 'La sortie ne doit pas ouvrir un second sejour.');

        $auDossier = HospitalisationDme::findOrFail($sejour->dme_hospitalization_id);

        $this->assertSame('discharged', $auDossier->status);
        $this->assertNotNull($auDossier->discharged_at);
    }

    // ---------------------------------------------------------- Les soins

    /**
     * Une prescription, un soin programme au dossier — pas neuf. WorkFlow
     * resout la recurrence a la creation, le dossier porte la prescription.
     */
    public function test_une_prescription_de_soins_donne_un_seul_soin_programme_au_dossier(): void
    {
        $service = Service::factory()->create();
        $medecin = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);
        $sejour = app(AdmitPatient::class)->execute($visit, $medecin);
        $type = CareTaskType::create(['name' => 'Perfusion', 'is_active' => true]);

        $debut = now()->addHour()->startOfHour();

        $occurrences = app(PrescribeCareTasks::class)->execute(
            hospitalization: $sejour,
            type: $type,
            doctor: $medecin,
            start: $debut,
            intervalHours: 8,
            durationDays: 3,
            instructions: 'Serum physiologique 500 ml',
        );

        $this->assertSame(9, $occurrences);
        $this->assertSame(1, CareOrder::count(), 'Le dossier porte la prescription, pas chaque administration.');

        $soin = CareOrder::firstOrFail();

        $this->assertSame('Perfusion', $soin->title);
        $this->assertSame('Serum physiologique 500 ml', $soin->instructions);
        $this->assertSame('Toutes les 8 h pendant 3 jour(s)', $soin->frequency);
        $this->assertSame('planned', $soin->status);
        $this->assertSame($sejour->dme_hospitalization_id, $soin->hospitalization_id);

        // Les neuf occurrences renvoient toutes vers ce meme soin.
        $this->assertSame(9, CareTask::where('dme_care_order_id', $soin->getKey())->count());
    }

    /**
     * Le soin ne se cloture qu'a la derniere administration : le marquer
     * « realise » a la premiere serait faux pour les huit suivantes.
     */
    public function test_le_soin_du_dossier_se_cloture_a_la_derniere_administration(): void
    {
        $service = Service::factory()->create();
        $medecin = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);
        $sejour = app(AdmitPatient::class)->execute($visit, $medecin);
        $type = CareTaskType::create(['name' => 'Pansement', 'is_active' => true]);

        app(PrescribeCareTasks::class)->execute(
            hospitalization: $sejour,
            type: $type,
            doctor: $medecin,
            start: now()->addHour(),
            intervalHours: 12,
            durationDays: 1,
        );

        $soins = CareTask::orderBy('scheduled_at')->get();
        $this->assertCount(2, $soins);

        // L'infirmier doit etre de garde sur le service pour marquer un soin.
        $infirmier = $this->makeNurse($service);

        app(CompleteCareTask::class)->execute($soins[0], $infirmier);

        $this->assertSame('planned', CareOrder::firstOrFail()->status, 'Une seule administration ne cloture rien.');

        app(CompleteCareTask::class)->markMissed($soins[1]->refresh(), $infirmier);

        $soin = CareOrder::firstOrFail();

        $this->assertSame('completed', $soin->status);
        $this->assertSame('1 administration(s) realisee(s), 1 manquee(s).', $soin->outcome);
        $this->assertNotNull($soin->completed_at);
    }

    // -------------------------------------------------------- Le cloisonnement

    /**
     * Ces trois projections ne doivent rien ecrire dans le dossier WorkFlow
     * qui porterait de la donnee de sante : le renvoi suffit.
     */
    public function test_le_dossier_medical_est_le_seul_a_porter_ces_actes(): void
    {
        $service = Service::factory()->create();
        $medecin = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        app(AdmitPatient::class)->execute($visit, $medecin);

        $this->assertSame(1, DB::table('dme_hospitalizations')->count());
        $this->assertSame(1, DB::table('hospitalizations')->count());
    }
}
