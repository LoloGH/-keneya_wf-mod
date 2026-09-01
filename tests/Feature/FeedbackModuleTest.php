<?php

namespace Tests\Feature;

use App\Actions\CloseVisit;
use App\Livewire\Admin\FeedbackViewer;
use App\Livewire\Portal\PatientFeedbackForm;
use App\Livewire\Portal\VisitorFeedbackForm;
use App\Livewire\Shared\IncidentReportForm;
use App\Models\FeedbackEntry;
use App\Models\Patient;
use App\Models\PatientHistory;
use App\Models\Service;
use App\Models\Setting;
use App\Models\SmsMessage;
use App\Models\Visit;
use App\Models\Visitor;
use App\Services\SmsGateway;
use App\Services\SmsSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Retours des patients et des visiteurs (v3.2.8, point 4).
 */
class FeedbackModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->mock(SmsGateway::class)->shouldReceive('deliver')->andReturn(SmsSendResult::sent());
    }

    // --------------------------------------------------- Envoi des liens

    /**
     * Le sondage part a la cloture, sans que personne ait a y penser : un
     * envoi qui depend d'une demande du personnel n'est jamais envoye.
     */
    public function test_un_sms_de_sondage_part_a_la_cloture_de_la_visite(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $medecin = $this->makeDoctor($service);
        $patient = Patient::factory()->create(['mobile' => '70112233']);

        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED], $patient);

        app(CloseVisit::class)->execute($visit, $medecin);

        $message = SmsMessage::where('to', '70112233')->sole();

        $this->assertStringContainsString($patient->portal_token, $message->body);
        $this->assertStringContainsString('avis', $message->body);
    }

    /** Le visiteur est sollicite apres le delai configure, et une seule fois. */
    public function test_le_lien_visiteur_part_apres_le_delai_et_jamais_deux_fois(): void
    {
        Setting::put(Setting::VISITOR_FEEDBACK_DELAY_HOURS, '3');

        $visitor = Visitor::factory()->create(['mobile' => '70445566']);

        // Trop tot : rien ne part.
        $this->artisan('keneya:liens-avis-visiteurs')->assertSuccessful();
        $this->assertSame(0, SmsMessage::where('to', '70445566')->count());
        $this->assertNull($visitor->fresh()->feedback_link_sent_at);

        $this->travel(4)->hours();

        $this->artisan('keneya:liens-avis-visiteurs')->assertSuccessful();

        $message = SmsMessage::where('to', '70445566')->sole();
        $this->assertStringContainsString($visitor->feedback_token, $message->body);
        $this->assertNotNull($visitor->fresh()->feedback_link_sent_at);

        // Un second passage ne renvoie rien.
        $this->travel(4)->hours();
        $this->artisan('keneya:liens-avis-visiteurs')->assertSuccessful();

        $this->assertSame(1, SmsMessage::where('to', '70445566')->count());
    }

    /**
     * Un visiteur sans numero ne doit ni faire echouer l'execution, ni empecher
     * les autres d'etre servis — et il doit rester comptabilise.
     */
    public function test_un_visiteur_sans_numero_n_interrompt_pas_les_autres_et_est_compte(): void
    {
        Setting::put(Setting::VISITOR_FEEDBACK_DELAY_HOURS, '3');

        $sansNumero = Visitor::factory()->create(['mobile' => null]);
        Visitor::factory()->create(['mobile' => '70445566']);

        $this->travel(4)->hours();

        $this->artisan('keneya:liens-avis-visiteurs')->assertSuccessful();

        // L'autre visiteur a bien recu le sien.
        $this->assertSame(1, SmsMessage::where('to', '70445566')->count());

        // Celui sans numero est marque traite, sans envoi : la tache ne doit
        // pas y revenir a chaque passage.
        $this->assertNotNull($sansNumero->fresh()->feedback_link_sent_at);
        $this->assertSame(1, SmsMessage::count());

        // Et il apparait dans le compteur dedie de /admin.
        Livewire::actingAs($this->makeAdmin())
            ->test(FeedbackViewer::class)
            ->assertSee('1 visiteur(s) sans numero de telephone');
    }

    // --------------------------------------------------- Depot des retours

    public function test_le_patient_note_son_passage_depuis_son_portail(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $medecin = $this->makeDoctor($service);
        $patient = Patient::factory()->create(['mobile' => '70112233']);

        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED], $patient);

        // Une consultation au dossier : c'est elle qui designe le medecin
        // concerne par la note « personnel ».
        PatientHistory::create([
            'patient_id' => $patient->getKey(),
            'visit_id' => $visit->getKey(),
            'type' => PatientHistory::TYPE_CONSULTATION,
            'service_id' => $service->getKey(),
            'doctor_id' => $medecin->getKey(),
            'description' => 'Consultation.',
        ]);

        Livewire::test(PatientFeedbackForm::class, ['patientId' => $patient->getKey()])
            ->set('ratingCare', 5)
            ->set('ratingStaff', 4)
            ->set('content', 'Accueil rapide et personnel attentionne.')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $entry = FeedbackEntry::sole();

        $this->assertSame(FeedbackEntry::TYPE_SURVEY, $entry->type);
        $this->assertSame($patient->getKey(), $entry->patient_id);
        $this->assertSame(5, $entry->rating_care);
        $this->assertSame(4, $entry->rating_staff);
        $this->assertSame($service->getKey(), $entry->service_id);
        // handled_by_user_id resolu depuis la derniere consultation.
        $this->assertSame($medecin->user_id, $entry->handled_by_user_id);
    }

    /** Une reclamation ne porte pas de notes : elles n'y ont pas de sens. */
    public function test_une_reclamation_exige_un_texte_et_ne_porte_aucune_note(): void
    {
        $patient = Patient::factory()->create();

        Livewire::test(PatientFeedbackForm::class, ['patientId' => $patient->getKey()])
            ->call('selectType', FeedbackEntry::TYPE_COMPLAINT)
            ->set('ratingCare', 5)
            ->call('submit')
            ->assertHasErrors('content');

        Livewire::test(PatientFeedbackForm::class, ['patientId' => $patient->getKey()])
            ->call('selectType', FeedbackEntry::TYPE_COMPLAINT)
            ->set('ratingCare', 5)
            ->set('content', 'Attente de plus de trois heures sans explication.')
            ->call('submit')
            ->assertHasNoErrors();

        $entry = FeedbackEntry::sole();

        $this->assertSame(FeedbackEntry::TYPE_COMPLAINT, $entry->type);
        $this->assertNull($entry->rating_care);
        $this->assertNull($entry->rating_staff);
    }

    public function test_le_visiteur_depose_son_avis_depuis_sa_page_dediee(): void
    {
        $accueil = $this->makeReceptionist();
        $service = Service::factory()->create(['name' => 'Medecine Generale']);

        $visitor = Visitor::factory()->create([
            'service_id' => $service->getKey(),
            'registered_by_user_id' => $accueil->getKey(),
        ]);

        $this->get(route('feedback.visitor', $visitor->feedback_token))->assertOk();

        Livewire::test(VisitorFeedbackForm::class, ['token' => $visitor->feedback_token])
            ->set('ratingCare', 4)
            ->set('ratingStaff', 5)
            ->set('content', 'Orientation claire des l\'entree.')
            ->call('submit')
            ->assertHasNoErrors();

        $entry = FeedbackEntry::sole();

        $this->assertSame($visitor->getKey(), $entry->visitor_id);
        $this->assertSame($service->getKey(), $entry->service_id);
        // Pour un visiteur, c'est l'agent d'accueil qui l'a recu.
        $this->assertSame($accueil->getKey(), $entry->handled_by_user_id);
    }

    /** Un jeton invente ne doit rien ouvrir. */
    public function test_un_jeton_de_visiteur_inconnu_renvoie_une_page_introuvable(): void
    {
        $this->get(route('feedback.visitor', 'jeton-invente'))->assertNotFound();
    }

    /** Le personnel depose un constat depuis son interface habituelle. */
    public function test_un_membre_du_personnel_depose_un_constat(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $medecin = $this->makeDoctor($service);
        $patient = Patient::factory()->create();

        Livewire::actingAs($medecin->user)
            ->test(IncidentReportForm::class, ['flashKey' => 'service.status'])
            ->set('patientCode', $patient->patient_code)
            ->set('serviceId', $service->getKey())
            ->set('content', 'Brancard manquant au service depuis deux jours.')
            ->call('submit')
            ->assertHasNoErrors();

        $entry = FeedbackEntry::sole();

        $this->assertSame(FeedbackEntry::TYPE_INCIDENT, $entry->type);
        $this->assertSame($medecin->user_id, $entry->submitted_by_user_id);
        $this->assertSame($patient->getKey(), $entry->patient_id);
        $this->assertNull($entry->rating_care);
    }

    // --------------------------------------------------- Administration

    /**
     * La verification demandee : passer une entree a « resolu » exige une note
     * de resolution, pas un changement de statut silencieux.
     */
    public function test_le_passage_a_resolu_exige_une_note_de_resolution(): void
    {
        $entry = FeedbackEntry::create([
            'type' => FeedbackEntry::TYPE_COMPLAINT,
            'content' => 'Attente trop longue.',
            'status' => FeedbackEntry::STATUS_NEW,
        ]);

        $admin = $this->makeAdmin();

        Livewire::actingAs($admin)
            ->test(FeedbackViewer::class)
            ->call('startResolution', $entry->getKey(), FeedbackEntry::STATUS_RESOLVED)
            ->call('resolve')
            ->assertHasErrors('resolutionNotes');

        $this->assertSame(FeedbackEntry::STATUS_NEW, $entry->fresh()->status);

        Livewire::actingAs($admin)
            ->test(FeedbackViewer::class)
            ->call('startResolution', $entry->getKey(), FeedbackEntry::STATUS_RESOLVED)
            ->set('resolutionNotes', 'File reorganisee, un agent supplementaire le matin.')
            ->call('resolve')
            ->assertHasNoErrors();

        $entry->refresh();

        $this->assertSame(FeedbackEntry::STATUS_RESOLVED, $entry->status);
        $this->assertSame($admin->getKey(), $entry->resolved_by_user_id);
        $this->assertNotNull($entry->resolved_at);
        $this->assertStringContainsString('File reorganisee', $entry->resolution_notes);
    }

    /**
     * Une entree doit se lire sans avoir a la croiser avec une autre page :
     * le contexte d'accueil du visiteur figure a cote du retour lui-meme.
     */
    public function test_l_administration_affiche_le_contexte_d_accueil(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $accueil = $this->makeReceptionist();

        $visitor = Visitor::factory()->create([
            'name' => 'Mariam Kone',
            'mobile' => '70998877',
            'service_id' => $service->getKey(),
            'registered_by_user_id' => $accueil->getKey(),
        ]);

        FeedbackEntry::create([
            'type' => FeedbackEntry::TYPE_SURVEY,
            'visitor_id' => $visitor->getKey(),
            'service_id' => $service->getKey(),
            'handled_by_user_id' => $accueil->getKey(),
            'rating_care' => 4,
            'rating_staff' => 5,
            'content' => 'Bon accueil.',
            'status' => FeedbackEntry::STATUS_NEW,
        ]);

        Livewire::actingAs($this->makeAdmin())
            ->test(FeedbackViewer::class)
            ->assertSee('Mariam Kone')
            ->assertSee('70998877')
            ->assertSee($visitor->visitor_code)
            ->assertSee('Medecine Generale')
            ->assertSee($accueil->name);
    }

    public function test_l_administration_filtre_par_type_et_par_statut(): void
    {
        FeedbackEntry::create([
            'type' => FeedbackEntry::TYPE_COMPLAINT,
            'content' => 'Une reclamation a traiter.',
            'status' => FeedbackEntry::STATUS_NEW,
        ]);

        FeedbackEntry::create([
            'type' => FeedbackEntry::TYPE_INCIDENT,
            'content' => 'Un constat deja resolu.',
            'status' => FeedbackEntry::STATUS_RESOLVED,
        ]);

        Livewire::actingAs($this->makeAdmin())
            ->test(FeedbackViewer::class)
            ->assertSee('Une reclamation a traiter.')
            ->assertSee('Un constat deja resolu.')
            ->set('type', FeedbackEntry::TYPE_COMPLAINT)
            ->assertSee('Une reclamation a traiter.')
            ->assertDontSee('Un constat deja resolu.')
            ->call('resetFilters')
            ->set('status', FeedbackEntry::STATUS_RESOLVED)
            ->assertSee('Un constat deja resolu.')
            ->assertDontSee('Une reclamation a traiter.');
    }

    /** Le delai se regle sans toucher au code. */
    public function test_le_delai_d_envoi_se_regle_depuis_l_administration(): void
    {
        Livewire::actingAs($this->makeAdmin())
            ->test(FeedbackViewer::class)
            ->assertSet('delayHours', Setting::DEFAULT_VISITOR_FEEDBACK_DELAY_HOURS)
            ->set('delayHours', 6)
            ->call('saveDelay')
            ->assertHasNoErrors();

        $this->assertSame('6', Setting::get(Setting::VISITOR_FEEDBACK_DELAY_HOURS));
    }
}
