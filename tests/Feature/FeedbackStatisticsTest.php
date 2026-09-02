<?php

namespace Tests\Feature;

use App\Livewire\Admin\PerformanceDashboard;
use App\Models\FeedbackEntry;
use App\Models\FeedbackSurveyRating;
use App\Models\Patient;
use App\Models\Service;
use App\Services\FeedbackStatistics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Les statistiques de satisfaction.
 *
 * Ce que ces tests protegent avant tout, ce n'est pas l'exactitude d'une
 * moyenne : c'est qu'aucune note ne soit attribuee a quelqu'un qui ne l'a pas
 * recue. Un tableau de bord qui note des personnes a le devoir d'etre juste
 * avant d'etre complet.
 */
class FeedbackStatisticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    private function sondage(Service $service, int $noteGlobale): FeedbackEntry
    {
        return FeedbackEntry::create([
            'type' => FeedbackEntry::TYPE_SURVEY,
            'patient_id' => Patient::factory()->create()->getKey(),
            'service_id' => $service->getKey(),
            'rating_care' => $noteGlobale,
            'status' => FeedbackEntry::STATUS_NEW,
        ]);
    }

    // --------------------------------------------------------- Vue d'ensemble

    public function test_l_entete_compte_chaque_type_de_retour_separement(): void
    {
        $service = Service::factory()->create();

        $this->sondage($service, 4);
        $this->sondage($service, 2);

        FeedbackEntry::create([
            'type' => FeedbackEntry::TYPE_COMPLAINT,
            'service_id' => $service->getKey(),
            'content' => 'Attente trop longue.',
            'status' => FeedbackEntry::STATUS_NEW,
        ]);

        $entete = app(FeedbackStatistics::class)->entete();

        $this->assertSame(2, $entete['sondages']);
        $this->assertSame(1, $entete['reclamations']);
        // La moyenne ne porte que sur les sondages : une reclamation n'a pas de
        // note, la compter comme un zero condamnerait le service.
        $this->assertSame(3.0, $entete['note_globale']);
    }

    // ------------------------------------------------------------ Par service

    public function test_un_service_sans_aucun_avis_figure_quand_meme(): void
    {
        Service::factory()->create(['name' => 'Echographie']);
        Service::factory()->create(['name' => 'Laboratoire']);

        $lignes = app(FeedbackStatistics::class)->parService();

        // Retirer les services muets donnerait un palmares ou ne resteraient
        // que ceux dont quelqu'un a parle. On verifie leur PRESENCE, pas un
        // compte exact : d'autres services peuvent exister par ailleurs, et un
        // test qui les compte casserait au premier ajout sans rien apprendre.
        $this->assertTrue($lignes->pluck('nom')->contains('Echographie'));
        $this->assertTrue($lignes->pluck('nom')->contains('Laboratoire'));
        $this->assertNull($lignes->firstWhere('nom', 'Laboratoire')['moyenne']);
        $this->assertSame(0, $lignes->firstWhere('nom', 'Laboratoire')['sondages']);
    }

    public function test_la_note_d_un_service_ne_deborde_pas_sur_un_autre(): void
    {
        $echographie = Service::factory()->create(['name' => 'Echographie']);
        $laboratoire = Service::factory()->create(['name' => 'Laboratoire']);

        $this->sondage($echographie, 5);
        $this->sondage($laboratoire, 1);

        $lignes = app(FeedbackStatistics::class)->parService();

        $this->assertSame(5.0, $lignes->firstWhere('nom', 'Echographie')['moyenne']);
        $this->assertSame(1.0, $lignes->firstWhere('nom', 'Laboratoire')['moyenne']);
    }

    // -------------------------------------------------------------- Par agent

    public function test_un_agent_ne_recoit_que_les_notes_qui_le_designent(): void
    {
        $service = Service::factory()->create();
        $vise = $this->makeDoctor($service);
        $autre = $this->makeDoctor($service);

        $entree = $this->sondage($service, 3);

        FeedbackSurveyRating::create([
            'feedback_entry_id' => $entree->getKey(),
            'user_id' => $vise->user_id,
            'post_label' => 'Medecin',
            'rating' => 5,
        ]);

        $lignes = app(FeedbackStatistics::class)->parAgent();

        $this->assertCount(1, $lignes, 'Seul l\'agent nommement note doit figurer.');
        $this->assertSame(5.0, $lignes->first()['moyenne']);
        $this->assertSame(1, $lignes->first()['notes']);

        // L'autre medecin du meme service ne doit apparaitre nulle part : la
        // note du service ne se repartit pas sur ses agents.
        $this->assertFalse($lignes->pluck('nom')->contains($autre->user->name));
    }

    public function test_un_effectif_trop_faible_est_signale_sans_etre_cache(): void
    {
        $service = Service::factory()->create();
        $agent = $this->makeDoctor($service);
        $entree = $this->sondage($service, 4);

        FeedbackSurveyRating::create([
            'feedback_entry_id' => $entree->getKey(),
            'user_id' => $agent->user_id,
            'post_label' => 'Medecin',
            'rating' => 5,
        ]);

        $ligne = app(FeedbackStatistics::class)->parAgent()->first();

        // Affichee, mais marquee : cacher la ligne reviendrait a choisir a la
        // place du lecteur ; ne rien signaler reviendrait a lui mentir.
        $this->assertFalse($ligne['fiable']);
        $this->assertSame(5.0, $ligne['moyenne']);
    }

    public function test_les_effectifs_solides_passent_devant(): void
    {
        $service = Service::factory()->create();
        $chanceux = $this->makeDoctor($service);
        $eprouve = $this->makeDoctor($service);

        $entree = $this->sondage($service, 4);

        // Une note parfaite unique.
        FeedbackSurveyRating::create([
            'feedback_entry_id' => $entree->getKey(),
            'user_id' => $chanceux->user_id,
            'post_label' => 'Medecin', 'rating' => 5,
        ]);

        // Six bonnes notes, moyenne plus basse mais assise.
        for ($i = 0; $i < 6; $i++) {
            FeedbackSurveyRating::create([
                'feedback_entry_id' => $entree->getKey(),
                'user_id' => $eprouve->user_id,
                'post_label' => 'Medecin', 'rating' => 4,
            ]);
        }

        $lignes = app(FeedbackStatistics::class)->parAgent();

        // 5,00 sur un avis ne doit pas troner en tete d'un classement de
        // personnes devant 4,00 sur six.
        $this->assertSame($eprouve->user->name, $lignes->first()['nom']);
    }

    // ------------------------------------------------------------- La periode

    public function test_la_periode_ecarte_les_retours_plus_anciens(): void
    {
        $service = Service::factory()->create();

        $ancien = $this->sondage($service, 1);
        $ancien->forceFill(['created_at' => now()->subDays(200)])->save();

        $this->sondage($service, 5);

        $stats = app(FeedbackStatistics::class);

        $this->assertSame(1, $stats->entete(90)['sondages']);
        $this->assertSame(5.0, $stats->entete(90)['note_globale']);
        $this->assertSame(2, $stats->entete()['sondages']);
    }

    // ------------------------------------------------------------- L'ecran

    public function test_l_ecran_d_analyse_s_affiche_sans_le_moindre_retour(): void
    {
        // Une installation neuve n'a aucun avis : l'ecran doit s'ouvrir, pas
        // tomber sur une division par zero.
        Service::factory()->create(['name' => 'Echographie']);

        Livewire::actingAs($this->makeAdmin())
            ->test(PerformanceDashboard::class)
            ->assertOk()
            ->assertSee('Echographie');
    }

    public function test_changer_de_periode_recalcule_l_ecran(): void
    {
        $service = Service::factory()->create();
        $ancien = $this->sondage($service, 5);
        $ancien->forceFill(['created_at' => now()->subDays(200)])->save();

        Livewire::actingAs($this->makeAdmin())
            ->test(PerformanceDashboard::class)
            ->set('periode', '30')
            ->assertViewHas('entete', fn (array $e) => $e['sondages'] === 0)
            ->set('periode', '')
            ->assertViewHas('entete', fn (array $e) => $e['sondages'] === 1);
    }
}
