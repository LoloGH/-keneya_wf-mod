<?php

namespace Tests\Feature;

use App\Actions\CloseVisit;
use App\Livewire\Admin\FeedbackViewer;
use App\Livewire\Admin\PatientDirectory;
use App\Livewire\Portal\PatientFeedbackForm;
use App\Livewire\Portal\VisitorFeedbackForm;
use App\Models\FeedbackEntry;
use App\Models\Patient;
use App\Models\PatientHistory;
use App\Models\Service;
use App\Models\SmsMessage;
use App\Models\Visit;
use App\Models\Visitor;
use App\Services\FeedbackJourney;
use App\Services\SmsGateway;
use App\Services\SmsSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sondage par etape du parcours (v3.2.9, point 3).
 *
 * Correction de conception du module livre en v3.2.8 : une note unique « le
 * personnel » melait dans un seul chiffre l'agent d'accueil, le caissier et le
 * medecin. Un patient tres bien recu mais mal oriente n'avait aucun moyen de le
 * dire, et l'administration aucun moyen de savoir ou agir.
 */
class FeedbackSurveyStepsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->mock(SmsGateway::class)->shouldReceive('deliver')->andReturn(SmsSendResult::sent());
    }

    private function etape(Visit $visit, Service $service, string $type, ?int $doctorId = null): void
    {
        PatientHistory::create([
            'patient_id' => $visit->patient_id,
            'visit_id' => $visit->getKey(),
            'type' => $type,
            'service_id' => $service->getKey(),
            'doctor_id' => $doctorId,
            'description' => 'Etape du parcours.',
        ]);
    }

    // ------------------------------------- Reconstitution du parcours

    /**
     * La verification demandee : une note distincte par etape reellement
     * traversee, et non une note globale unique de personnel.
     */
    public function test_le_parcours_est_reconstitue_depuis_le_dossier(): void
    {
        $accueil = Service::factory()->create(['name' => 'Accueil']);
        $medecine = Service::factory()->create(['name' => 'Medecine Generale']);
        $medecin = $this->makeDoctor($medecine);

        $visit = $this->makeVisit($accueil);
        $this->etape($visit, $accueil, PatientHistory::TYPE_REGISTRATION);
        $this->etape($visit, $medecine, PatientHistory::TYPE_CONSULTATION, $medecin->getKey());
        // Le meme medecin revu : une seule etape, pas deux.
        $this->etape($visit, $medecine, PatientHistory::TYPE_CONSULTATION_CONCLUSION, $medecin->getKey());

        $etapes = app(FeedbackJourney::class)->steps($visit);

        $this->assertCount(2, $etapes);
        $this->assertSame('Accueil', $etapes[0]['label']);
        $this->assertNull($etapes[0]['user_id'], "L'accueil n'identifie personne : la note porte sur le poste.");
        $this->assertStringContainsString('Medecine Generale', $etapes[1]['label']);
        $this->assertSame($medecin->user_id, $etapes[1]['user_id']);
    }

    /**
     * Un sondage lance avant la cloture ne montre que ce qui a deja eu lieu.
     * Ce n'est pas un cas particulier : c'est la lecture du dossier a cet
     * instant.
     */
    public function test_un_sondage_lance_en_cours_de_parcours_ne_montre_que_les_etapes_franchies(): void
    {
        $accueil = Service::factory()->create(['name' => 'Accueil']);
        $medecine = Service::factory()->create(['name' => 'Medecine Generale']);
        $medecin = $this->makeDoctor($medecine);

        $visit = $this->makeVisit($accueil);
        $this->etape($visit, $accueil, PatientHistory::TYPE_REGISTRATION);

        $journee = app(FeedbackJourney::class);
        $this->assertCount(1, $journee->steps($visit));

        // La consultation a lieu ensuite : l'etape apparait alors.
        $this->etape($visit, $medecine, PatientHistory::TYPE_CONSULTATION, $medecin->getKey());

        $this->assertCount(2, $journee->steps($visit->fresh()));
    }

    // ------------------------------------- Saisie du sondage

    public function test_le_patient_note_chaque_poste_et_la_note_personnel_en_est_la_moyenne(): void
    {
        $accueil = Service::factory()->create(['name' => 'Accueil']);
        $medecine = Service::factory()->create(['name' => 'Medecine Generale']);
        $medecin = $this->makeDoctor($medecine);

        $patient = Patient::factory()->create(['mobile' => '70112233']);
        $visit = $this->makeVisit($accueil, [], $patient);
        $this->etape($visit, $accueil, PatientHistory::TYPE_REGISTRATION);
        $this->etape($visit, $medecine, PatientHistory::TYPE_CONSULTATION, $medecin->getKey());

        Livewire::test(PatientFeedbackForm::class, ['patientId' => $patient->getKey()])
            ->set('ratingCare', 4)
            ->set('stepRatings.'.$accueil->getKey().':0', 2)
            ->set('stepRatings.'.$medecine->getKey().':'.$medecin->user_id, 5)
            ->set('stepComments.'.$accueil->getKey().':0', 'Longue attente au guichet.')
            ->call('submit')
            ->assertHasNoErrors();

        $entree = FeedbackEntry::sole();
        $notes = $entree->surveyRatings()->orderBy('id')->get();

        $this->assertCount(2, $notes);
        $this->assertSame(2, $notes[0]->rating);
        $this->assertSame('Longue attente au guichet.', $notes[0]->comment);
        $this->assertSame(5, $notes[1]->rating);
        $this->assertSame($medecin->user_id, $notes[1]->user_id);

        // rating_staff n'est plus saisi : il vaut la moyenne des etapes.
        $this->assertSame(4, $entree->rating_care);
        $this->assertSame(4, $entree->rating_staff, 'La moyenne de 2 et 5 arrondie vaut 4.');
    }

    /** Aucune etape n'est obligatoire : un avis partiel doit pouvoir partir. */
    public function test_un_sondage_sans_aucune_note_d_etape_reste_envoyable(): void
    {
        $service = Service::factory()->create(['name' => 'Accueil']);
        $patient = Patient::factory()->create(['mobile' => '70112233']);
        $visit = $this->makeVisit($service, [], $patient);
        $this->etape($visit, $service, PatientHistory::TYPE_REGISTRATION);

        Livewire::test(PatientFeedbackForm::class, ['patientId' => $patient->getKey()])
            ->set('ratingCare', 5)
            ->set('content', 'Rien a signaler.')
            ->call('submit')
            ->assertHasNoErrors();

        $entree = FeedbackEntry::sole();

        $this->assertSame(0, $entree->surveyRatings()->count());
        $this->assertNull($entree->rating_staff);
    }

    /** Une reclamation ne porte toujours aucune note, meme par etape. */
    public function test_une_reclamation_ne_porte_aucune_note_d_etape(): void
    {
        $service = Service::factory()->create(['name' => 'Accueil']);
        $patient = Patient::factory()->create(['mobile' => '70112233']);
        $visit = $this->makeVisit($service, [], $patient);
        $this->etape($visit, $service, PatientHistory::TYPE_REGISTRATION);

        Livewire::test(PatientFeedbackForm::class, ['patientId' => $patient->getKey()])
            ->call('selectType', FeedbackEntry::TYPE_COMPLAINT)
            ->set('stepRatings.'.$service->getKey().':0', 1)
            ->set('content', 'Attente de plus de trois heures sans explication.')
            ->call('submit')
            ->assertHasNoErrors();

        $entree = FeedbackEntry::sole();

        $this->assertSame(0, $entree->surveyRatings()->count());
        $this->assertNull($entree->rating_staff);
    }

    // ------------------------------------- Declenchement manuel

    /**
     * La verification demandee : l'action fonctionne avant la cloture, et
     * n'empeche pas un sondage automatique ulterieur sur le meme patient.
     */
    public function test_l_admin_lance_un_sondage_avant_la_cloture(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $patient = Patient::factory()->create(['mobile' => '70112233']);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED], $patient);

        $this->assertFalse($visit->isClosed());

        Livewire::actingAs($this->makeAdmin())
            ->test(PatientDirectory::class)
            ->call('openRecord', $patient->getKey())
            ->call('launchSurvey', $patient->getKey());

        $message = SmsMessage::where('to', '70112233')->sole();
        $this->assertStringContainsString($patient->portal_token, $message->body);

        // La cloture enverra le sien : le declenchement manuel ne le bloque pas.
        app(CloseVisit::class)->execute($visit->fresh(), $this->makeDoctor($service));

        $this->assertSame(2, SmsMessage::where('to', '70112233')->count());
    }

    /** Deux sondages successifs restent consultables separement. */
    public function test_deux_passages_donnent_deux_sondages_qui_ne_s_ecrasent_pas(): void
    {
        // La regle de la v3.2.9 tient toujours : un second sondage n'ecrase
        // jamais le premier. Ce qui change, c'est qu'il faut un nouveau passage
        // pour y avoir droit.
        $service = Service::factory()->create(['name' => 'Accueil']);
        $patient = Patient::factory()->create(['mobile' => '70112233']);

        foreach ([[3, '-2 days'], [5, 'now']] as [$note, $quand]) {
            $visite = $this->makeVisit($service, ['opened_at' => now()->parse($quand)], $patient);
            $this->etape($visite, $service, PatientHistory::TYPE_REGISTRATION);

            Livewire::test(PatientFeedbackForm::class, ['patientId' => $patient->getKey()])
                ->set('ratingCare', $note)
                ->set('stepRatings.'.$service->getKey().':0', $note)
                ->call('submit')
                ->assertHasNoErrors();
        }

        $entrees = FeedbackEntry::where('patient_id', $patient->getKey())->orderBy('id')->get();

        $this->assertCount(2, $entrees);
        $this->assertSame(3, $entrees[0]->rating_care);
        $this->assertSame(5, $entrees[1]->rating_care);

        // Et chacune est rattachee a son propre passage.
        $this->assertNotSame($entrees[0]->visit_id, $entrees[1]->visit_id);
    }

    public function test_un_seul_sondage_par_passage(): void
    {
        $service = Service::factory()->create(['name' => 'Accueil']);
        $patient = Patient::factory()->create(['mobile' => '70112233']);
        $visite = $this->makeVisit($service, [], $patient);
        $this->etape($visite, $service, PatientHistory::TYPE_REGISTRATION);

        $composant = Livewire::test(PatientFeedbackForm::class, ['patientId' => $patient->getKey()])
            ->set('ratingCare', 4)
            ->call('submit')
            ->assertHasNoErrors();

        // Le formulaire bascule de lui-meme sur la reclamation : le sondage de
        // ce passage est clos.
        $composant->assertSet('type', FeedbackEntry::TYPE_COMPLAINT);

        // Et une seconde tentative forgee — deux onglets ouverts, un lien SMS
        // reclique — n'ecrit rien de plus.
        $composant->set('type', FeedbackEntry::TYPE_SURVEY)
            ->set('ratingCare', 1)
            ->call('submit');

        $sondages = FeedbackEntry::where('patient_id', $patient->getKey())
            ->where('type', FeedbackEntry::TYPE_SURVEY)
            ->get();

        $this->assertCount(1, $sondages);
        $this->assertSame(4, $sondages->first()->rating_care);
    }

    public function test_la_reclamation_reste_ouverte_apres_le_sondage(): void
    {
        // « Seule la reclamation peut rester » : on peut avoir note son passage
        // et decouvrir un probleme le lendemain.
        $service = Service::factory()->create(['name' => 'Accueil']);
        $patient = Patient::factory()->create(['mobile' => '70112233']);
        $visite = $this->makeVisit($service, [], $patient);
        $this->etape($visite, $service, PatientHistory::TYPE_REGISTRATION);

        Livewire::test(PatientFeedbackForm::class, ['patientId' => $patient->getKey()])
            ->set('ratingCare', 4)
            ->call('submit')
            ->set('content', 'Le guichet etait ferme sans explication.')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame(
            1,
            FeedbackEntry::where('patient_id', $patient->getKey())
                ->where('type', FeedbackEntry::TYPE_COMPLAINT)
                ->count(),
        );
    }

    public function test_un_patient_sans_telephone_ne_declenche_aucun_envoi(): void
    {
        $patient = Patient::factory()->create(['mobile' => '']);

        Livewire::actingAs($this->makeAdmin())
            ->test(PatientDirectory::class)
            ->call('launchSurvey', $patient->getKey());

        $this->assertSame(0, SmsMessage::count());
    }

    /** Le meme geste existe pour un visiteur, depuis la section « Retours ». */
    public function test_l_admin_lance_un_sondage_visiteur_a_la_demande(): void
    {
        $visitor = Visitor::factory()->create(['mobile' => '70445566']);

        Livewire::actingAs($this->makeAdmin())
            ->test(FeedbackViewer::class)
            ->call('launchVisitorSurvey', $visitor->getKey());

        $message = SmsMessage::where('to', '70445566')->sole();

        $this->assertStringContainsString($visitor->feedback_token, $message->body);
        // Marque comme servi : la tache planifiee ne doit pas renvoyer un second lien.
        $this->assertNotNull($visitor->fresh()->feedback_link_sent_at);
    }

    // ------------------------------------------- Sondage borne a la session

    public function test_le_visiteur_ne_note_qu_une_fois_par_venue(): void
    {
        $visitor = Visitor::factory()->create(['mobile' => '70445566']);

        $composant = Livewire::test(VisitorFeedbackForm::class, ['token' => $visitor->feedback_token])
            ->set('ratingCare', 4)
            ->set('ratingStaff', 5)
            ->call('submit')
            ->assertHasNoErrors();

        $composant->assertSet('type', FeedbackEntry::TYPE_COMPLAINT);

        // Le lien recu par SMS reste cliquable : une seconde soumission forgee
        // ne doit rien ecrire de plus.
        $composant->set('type', FeedbackEntry::TYPE_SURVEY)
            ->set('ratingCare', 1)
            ->set('ratingStaff', 1)
            ->call('submit');

        $this->assertSame(
            1,
            FeedbackEntry::where('visitor_id', $visitor->getKey())
                ->where('type', FeedbackEntry::TYPE_SURVEY)
                ->count(),
        );
    }

    public function test_l_admin_ne_relance_pas_un_sondage_deja_donne(): void
    {
        // Envoyer le lien couterait un SMS pour mener a une page qui ne
        // proposerait plus rien.
        $service = Service::factory()->create(['name' => 'Accueil']);
        $patient = Patient::factory()->create(['mobile' => '70112233']);
        $visite = $this->makeVisit($service, [], $patient);
        $this->etape($visite, $service, PatientHistory::TYPE_REGISTRATION);

        Livewire::test(PatientFeedbackForm::class, ['patientId' => $patient->getKey()])
            ->set('ratingCare', 4)
            ->call('submit')
            ->assertHasNoErrors();

        SmsMessage::query()->delete();

        Livewire::actingAs($this->makeAdmin())
            ->test(PatientDirectory::class)
            ->call('launchSurvey', $patient->getKey());

        $this->assertSame(0, SmsMessage::where('to', '70112233')->count());
    }
}
