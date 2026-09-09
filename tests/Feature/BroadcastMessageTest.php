<?php

namespace Tests\Feature;

use App\Actions\SendBroadcast;
use App\Jobs\SendSmsJob;
use App\Livewire\Admin\BroadcastComposer;
use App\Models\BroadcastMessage;
use App\Models\Pathology;
use App\Models\Patient;
use App\Models\PatientHistory;
use App\Models\Service;
use App\Models\SmsMessage;
use App\Services\BroadcastRecipients;
use App\Services\SmsGateway;
use App\Services\SmsSendResult;
use App\Support\Audit;
use App\Support\BroadcastTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Envoi groupe de SMS (v3.2.9, point 1).
 *
 * Le fil conducteur : un envoi « en masse » doit rester honnete sur ce qu'il
 * s'apprete a faire, et tenir la charge d'un vrai fichier patient.
 */
class BroadcastMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->mock(SmsGateway::class)->shouldReceive('deliver')->andReturn(SmsSendResult::sent());
    }

    // ------------------------------------------------- Ciblage

    public function test_un_groupe_retient_les_patients_passes_par_le_service_meme_apres_un_renvoi(): void
    {
        $radiologie = Service::factory()->create(['name' => 'Radiologie']);
        $medecine = Service::factory()->create(['name' => 'Medecine Generale']);

        // Passe par la radiologie, puis reparti ailleurs : sa visite ne porte
        // plus ce service, mais son historique si.
        $passe = Patient::factory()->create(['mobile' => '70111111']);
        $visite = $this->makeVisit($medecine, [], $passe);
        PatientHistory::create([
            'patient_id' => $passe->getKey(),
            'visit_id' => $visite->getKey(),
            'type' => PatientHistory::TYPE_CONSULTATION,
            'service_id' => $radiologie->getKey(),
            'description' => 'Echographie realisee.',
        ]);

        // N'y est jamais passe.
        Patient::factory()->create(['mobile' => '70222222']);

        $destinataires = app(BroadcastRecipients::class);
        $cible = new BroadcastTarget(
            type: BroadcastMessage::TARGET_PATIENT_GROUP,
            serviceId: $radiologie->getKey(),
        );

        $this->assertSame(1, $destinataires->count($cible));
    }

    public function test_un_patient_sans_telephone_n_est_jamais_compte_ni_sollicite(): void
    {
        Patient::factory()->create(['mobile' => '70111111']);
        Patient::factory()->create(['mobile' => '']);

        $cible = new BroadcastTarget(type: BroadcastMessage::TARGET_ALL_PATIENTS);

        $this->assertSame(1, app(BroadcastRecipients::class)->count($cible));
    }

    /** Le filtre pathologie affine le groupe sans le remplacer. */
    public function test_le_filtre_pathologie_restreint_le_groupe(): void
    {
        $service = Service::factory()->create();
        $pathologie = Pathology::create(['name' => 'Hypertension arterielle']);

        $suivi = Patient::factory()->create(['mobile' => '70111111']);
        $this->makeVisit($service, ['pathology_id' => $pathologie->getKey()], $suivi);

        $autre = Patient::factory()->create(['mobile' => '70222222']);
        $this->makeVisit($service, [], $autre);

        $destinataires = app(BroadcastRecipients::class);

        $this->assertSame(2, $destinataires->count(
            new BroadcastTarget(type: BroadcastMessage::TARGET_ALL_PATIENTS),
        ));

        $this->assertSame(1, $destinataires->count(
            new BroadcastTarget(
                type: BroadcastMessage::TARGET_ALL_PATIENTS,
                pathologyId: $pathologie->getKey(),
            ),
        ));
    }

    /**
     * Le personnel non medecin doit etre joignable : c'etait impossible avant
     * `users.mobile`, seuls les medecins ayant un telephone.
     */
    public function test_un_membre_du_personnel_non_medecin_est_joignable(): void
    {
        $receptionniste = $this->makeReceptionist();
        $receptionniste->forceFill(['mobile' => '70334455'])->save();

        $cible = new BroadcastTarget(
            type: BroadcastMessage::TARGET_STAFF,
            staffUserId: $receptionniste->getKey(),
        );

        $this->assertSame(1, app(BroadcastRecipients::class)->count($cible));
        $this->assertSame('70334455', $receptionniste->smsNumber());
    }

    /** Les numeros deja saisis sur les fiches medecin continuent de servir. */
    public function test_le_numero_de_la_fiche_medecin_sert_de_repli(): void
    {
        $medecin = $this->makeDoctor(Service::factory()->create(), '70998877');

        $this->assertSame('70998877', $medecin->user->smsNumber());
    }

    // ------------------------------------------------- Envoi

    /**
     * La verification demandee : un envoi « a tous » traite la selection par
     * lots. 600 patients depassent la taille de lot (500), ce qui force
     * plusieurs passages : un `get()` unique passerait aussi ce test, mais
     * seul le parcours par lots le passe sans charger la table entiere.
     */
    public function test_un_envoi_a_tous_les_patients_traite_la_selection_par_lots(): void
    {
        Queue::fake();

        Patient::factory()->count(600)->create();

        $diffusion = app(SendBroadcast::class)->execute(
            $this->makeAdmin(),
            'Campagne de vaccination du 12 au 16 mars.',
            new BroadcastTarget(type: BroadcastMessage::TARGET_ALL_PATIENTS),
        );

        $this->assertSame(600, $diffusion->recipient_count);
        Queue::assertPushed(SendSmsJob::class, 600);
        $this->assertSame(600, SmsMessage::count());
    }

    /** Chaque SMS est rattache a la diffusion, pas au patient. */
    public function test_chaque_sms_est_rattache_a_la_diffusion(): void
    {
        Queue::fake();

        Patient::factory()->count(3)->create(['mobile' => '70111111']);

        $diffusion = app(SendBroadcast::class)->execute(
            $this->makeAdmin(),
            'Message de test pour la diffusion.',
            new BroadcastTarget(type: BroadcastMessage::TARGET_ALL_PATIENTS),
        );

        $this->assertSame(3, $diffusion->smsMessages()->count());
        $this->assertSame(BroadcastMessage::class, SmsMessage::first()->related_type);
    }

    public function test_une_diffusion_est_journalisee_avec_son_contenu_et_son_volume(): void
    {
        Queue::fake();

        Patient::factory()->count(4)->create();
        $admin = $this->makeAdmin();

        // Le journal attribue l'action a l'utilisateur authentifie, comme
        // partout ailleurs dans le projet : on se place donc dans les memes
        // conditions que l'ecran d'administration.
        $this->actingAs($admin);

        app(SendBroadcast::class)->execute(
            $admin,
            'La pharmacie sera fermee samedi.',
            new BroadcastTarget(type: BroadcastMessage::TARGET_ALL_PATIENTS),
        );

        $trace = Activity::where('event', Audit::EVENT_BROADCAST_SENT)->sole();

        $this->assertSame($admin->getKey(), $trace->causer_id);
        $this->assertStringContainsString('4 destinataire', $trace->description);
        $this->assertSame('La pharmacie sera fermee samedi.', $trace->properties['message']);
    }

    public function test_une_diffusion_sans_destinataire_joignable_est_refusee(): void
    {
        Queue::fake();

        Patient::factory()->create(['mobile' => '']);

        $this->expectException(\InvalidArgumentException::class);

        app(SendBroadcast::class)->execute(
            $this->makeAdmin(),
            'Message sans destinataire.',
            new BroadcastTarget(type: BroadcastMessage::TARGET_ALL_PATIENTS),
        );
    }

    // ------------------------------------------------- Interface

    /**
     * Le point qui compte pour un envoi de masse : rien ne part avant que
     * l'administrateur ait vu combien de personnes il s'apprete a joindre.
     */
    public function test_rien_ne_part_sans_apercu_prealable_du_nombre_de_destinataires(): void
    {
        Queue::fake();

        Patient::factory()->count(5)->create();

        Livewire::actingAs($this->makeAdmin())
            ->test(BroadcastComposer::class)
            ->set('content', 'Campagne de vaccination du 12 au 16 mars.')
            ->set('targetType', BroadcastMessage::TARGET_ALL_PATIENTS)
            ->call('send')
            ->assertSet('previewCount', null);

        $this->assertSame(0, BroadcastMessage::count());
        Queue::assertNothingPushed();
    }

    public function test_l_apercu_annonce_le_nombre_exact_puis_l_envoi_part(): void
    {
        Queue::fake();

        Patient::factory()->count(5)->create();
        Patient::factory()->create(['mobile' => '']);

        Livewire::actingAs($this->makeAdmin())
            ->test(BroadcastComposer::class)
            ->set('content', 'Campagne de vaccination du 12 au 16 mars.')
            ->set('targetType', BroadcastMessage::TARGET_ALL_PATIENTS)
            ->call('preview')
            ->assertSet('previewCount', 5)
            ->call('send')
            ->assertHasNoErrors();

        $this->assertSame(5, BroadcastMessage::sole()->recipient_count);
        Queue::assertPushed(SendSmsJob::class, 5);
    }

    /** Changer un filtre apres l'apercu invalide le nombre confirme. */
    public function test_modifier_la_cible_apres_l_apercu_invalide_le_decompte(): void
    {
        Patient::factory()->count(5)->create();

        Livewire::actingAs($this->makeAdmin())
            ->test(BroadcastComposer::class)
            ->set('content', 'Campagne de vaccination du 12 au 16 mars.')
            ->set('targetType', BroadcastMessage::TARGET_ALL_PATIENTS)
            ->call('preview')
            ->assertSet('previewCount', 5)
            ->set('content', 'Un tout autre message, envoye par erreur.')
            ->assertSet('previewCount', null);
    }
}
