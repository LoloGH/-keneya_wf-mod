<?php

namespace Tests\Feature;

use App\Actions\RegisterPatient;
use App\Jobs\SendSmsJob;
use App\Livewire\Admin\SmsMessageViewer;
use App\Models\Patient;
use App\Models\Service;
use App\Models\SmsMessage;
use App\Services\SmsGateway;
use App\Services\SmsSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * File d'attente SMS (v3.2.8).
 *
 * Le point verifie ici n'est pas « le SMS part », il partait deja, mais
 * « l'acte metier ne l'attend plus », et « un echec laisse une trace que
 * quelqu'un voit ».
 */
class SmsQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    /**
     * Le coeur du changement : l'enregistrement se termine sans qu'aucun appel
     * n'ait ete fait a la passerelle. Le SMS attend dans la file.
     */
    public function test_l_enregistrement_d_un_patient_n_appelle_plus_la_passerelle(): void
    {
        Queue::fake();
        Http::preventStrayRequests();

        $service = Service::factory()->create(['name' => 'Medecine Generale']);

        $visit = app(RegisterPatient::class)->execute([
            'name' => 'Awa Traore',
            'age' => 34,
            'gender' => 'Femme',
            'mobile' => '70112233',
            'service_id' => $service->getKey(),
        ]);

        // L'acte metier est complet : dossier cree, ticket attribue.
        $this->assertNotNull($visit->patient->patient_code);

        Queue::assertPushed(SendSmsJob::class, 1);

        // Et la trace existe deja, en file, avant tout appel reseau.
        $message = SmsMessage::sole();
        $this->assertSame(SmsMessage::STATUS_QUEUED, $message->status);
        $this->assertSame('70112233', $message->to);
        $this->assertStringContainsString($visit->patient->patient_code, $message->body);
        $this->assertSame(Patient::class, $message->related_type);
        $this->assertSame($visit->patient->getKey(), $message->related_id);
    }

    /**
     * Passerelle volontairement injoignable : l'enregistrement doit aboutir
     * exactement comme si elle repondait. C'est la verification demandee avant
     * livraison, et elle ne se deduit pas du simple remplacement de l'appel.
     */
    public function test_un_enregistrement_aboutit_meme_passerelle_indisponible(): void
    {
        config(['queue.default' => 'database', 'services.smsgate.enabled' => true]);

        // La passerelle ne repond pas du tout, comme un telephone endormi.
        Http::fake(['*' => Http::failedConnection()]);

        $service = Service::factory()->create(['name' => 'Medecine Generale']);

        $visit = app(RegisterPatient::class)->execute([
            'name' => 'Modibo Keita',
            'age' => 51,
            'gender' => 'Homme',
            'mobile' => '70445566',
            'service_id' => $service->getKey(),
        ]);

        $this->assertNotNull($visit->patient->patient_code);
        $this->assertSame(SmsMessage::STATUS_QUEUED, SmsMessage::sole()->status);

        // Aucun appel n'a ete tente pendant l'enregistrement : c'est le worker,
        // plus tard, qui se heurtera a la passerelle muette.
        Http::assertNothingSent();
    }

    public function test_un_envoi_accepte_passe_a_envoye_et_conserve_l_identifiant_de_la_passerelle(): void
    {
        // La file est mise de cote pour executer le job a la main : sous le
        // pilote `sync` il partirait des la mise en file, et la passerelle
        // serait appelee deux fois.
        Queue::fake();

        $this->mock(SmsGateway::class)
            ->shouldReceive('deliver')
            ->once()
            ->andReturn(SmsSendResult::sent('msg-42'));

        $message = SendSmsJob::dispatch('70112233', 'Bonjour');

        (new SendSmsJob($message->getKey()))->handle(app(SmsGateway::class));

        $message->refresh();
        $this->assertSame(SmsMessage::STATUS_SENT, $message->status);
        $this->assertSame('msg-42', $message->provider_message_id);
        $this->assertNotNull($message->sent_at);
        $this->assertNull($message->failure_reason);
    }

    /**
     * Trois tentatives espacees, puis echec definitif consigne avec sa raison.
     * Le test fait reellement tourner le worker plutot que d'appeler `failed()`
     * a la main : c'est le comptage des tentatives par la file que l'on veut
     * verifier, pas seulement l'ecriture du statut.
     */
    public function test_un_envoi_qui_echoue_trois_fois_finit_en_echec_avec_sa_raison(): void
    {
        config(['queue.default' => 'database']);

        $this->mock(SmsGateway::class)
            ->shouldReceive('deliver')
            ->times(3)
            ->andReturn(SmsSendResult::failed('Passerelle injoignable : timeout.'));

        $message = SendSmsJob::dispatch('70112233', 'Bonjour');

        // Tentative 1, puis les attentes de 30 s, 60 s et 120 s.
        foreach ([30, 60, 120] as $attente) {
            $this->travailleUnJob();
            $this->assertSame(
                SmsMessage::STATUS_QUEUED,
                $message->fresh()->status,
                'Le message ne doit pas etre declare en echec avant la troisieme tentative.',
            );

            $this->travel($attente + 1)->seconds();
        }

        // Quatrieme passage : les trois tentatives sont epuisees.
        $this->travailleUnJob();

        $message->refresh();
        $this->assertSame(SmsMessage::STATUS_FAILED, $message->status);
        $this->assertSame('Passerelle injoignable : timeout.', $message->failure_reason);
        $this->assertSame(3, $message->attempts);
        $this->assertDatabaseCount('failed_jobs', 1);
    }

    /**
     * Une passerelle desactivee ne repondra pas davantage a la troisieme
     * tentative qu'a la premiere : l'echec est immediat.
     */
    public function test_un_echec_definitif_ne_consomme_pas_les_trois_tentatives(): void
    {
        config(['queue.default' => 'database', 'services.smsgate.enabled' => false]);

        $message = SendSmsJob::dispatch('70112233', 'Bonjour');

        $this->travailleUnJob();

        $message->refresh();
        $this->assertSame(SmsMessage::STATUS_FAILED, $message->status);
        $this->assertStringContainsString('desactivee', $message->failure_reason);
    }

    public function test_un_numero_absent_ne_cree_aucune_trace_ni_aucun_job(): void
    {
        Queue::fake();

        $this->assertNull(SendSmsJob::dispatch(null, 'Bonjour'));
        $this->assertNull(SendSmsJob::dispatch('', 'Bonjour'));

        $this->assertSame(0, SmsMessage::count());
        Queue::assertNothingPushed();
    }

    public function test_l_administration_liste_les_sms_et_filtre_les_echecs(): void
    {
        $this->creerMessage(SmsMessage::STATUS_SENT, '70111111');
        $this->creerMessage(SmsMessage::STATUS_FAILED, '70222222', 'Passerelle injoignable.');

        Livewire::actingAs($this->makeAdmin())
            ->test(SmsMessageViewer::class)
            ->assertSee('70111111')
            ->assertSee('70222222')
            ->call('showFailures')
            ->assertSet('status', SmsMessage::STATUS_FAILED)
            ->assertSee('70222222')
            ->assertDontSee('70111111')
            ->assertSee('Passerelle injoignable.');
    }

    /**
     * L'indicateur qui rend l'echec visible sans ouvrir la liste. Un echec plus
     * ancien que 24 h n'y figure plus : c'est une alerte d'exploitation, pas un
     * cumul historique.
     */
    public function test_le_compteur_ne_retient_que_les_echecs_des_dernieres_24_heures(): void
    {
        $this->creerMessage(SmsMessage::STATUS_FAILED, '70222222');
        $this->creerMessage(SmsMessage::STATUS_SENT, '70333333');

        $ancien = $this->creerMessage(SmsMessage::STATUS_FAILED, '70444444');
        $ancien->forceFill(['created_at' => now()->subDays(2)])->save();

        $this->assertSame(1, SmsMessage::recentFailureCount());

        Livewire::actingAs($this->makeAdmin())
            ->test(SmsMessageViewer::class)
            ->assertSee("echec d'envoi dans les dernieres 24 h");
    }

    /** Traite un seul job de la file, comme le ferait le conteneur worker. */
    private function travailleUnJob(): void
    {
        $this->artisan('queue:work', [
            '--once' => true,
            '--sleep' => 0,
            '--tries' => 3,
        ]);
    }

    private function creerMessage(string $status, string $to, ?string $raison = null): SmsMessage
    {
        return SmsMessage::create([
            'to' => $to,
            'body' => 'Message de test.',
            'status' => $status,
            'failure_reason' => $raison,
        ]);
    }
}
