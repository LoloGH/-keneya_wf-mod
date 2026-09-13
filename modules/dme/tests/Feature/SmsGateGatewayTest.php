<?php

declare(strict_types=1);

namespace Keneya\Dme\Tests\Feature;

use Keneya\Dme\Models\SmsMessage;
use Keneya\Dme\Sms\Pipeline\Gateways\SmsGateGateway;
use Keneya\Dme\Sms\Pipeline\SmsGatewayManager;
use Keneya\Dme\Sms\Pipeline\SmsResult;
use Keneya\Dme\Sms\Pipeline\SmsService;
use Keneya\Dme\Sms\Pipeline\TracksDeliveryStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Keneya\Dme\Tests\TestCase;

/**
 * Passerelle SMSGate : passerelle de production du projet.
 *
 * Les échanges HTTP sont simulés : la suite valide le contrat REST
 * (endpoint, authentification, charge utile), la correspondance des
 * états d'acheminement et l'absence de fuite de secret.
 */
class SmsGateGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://sms.example.test/3rdparty/v1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
        $this->useSmsGate();
    }

    /**
     * Bascule l'application sur SMSGate avec des identifiants factices.
     */
    private function useSmsGate(array $overrides = []): void
    {
        config()->set('dme.sms.gateway', 'smsgate');
        config()->set('dme.sms.gateways.smsgate', array_merge([
            'driver' => 'smsgate',
            'base_url' => self::BASE,
            'username' => 'utilisateur-test',
            'password' => 'secret-tres-confidentiel',
            'token' => null,
            'sim_number' => null,
            'with_delivery_report' => true,
            'timeout' => 5,
            'ttl' => null,
            'verify_tls' => true,
        ], $overrides));

        // Le gestionnaire est un singleton : il faut le réinitialiser
        // pour qu'il reprenne la configuration modifiée.
        app()->forgetInstance(SmsGatewayManager::class);
    }

    private function gateway(): SmsGateGateway
    {
        return app(SmsGatewayManager::class)->gateway();
    }

    // -----------------------------------------------------------------
    // Contrat REST
    // -----------------------------------------------------------------

    public function test_la_passerelle_smsgate_est_bien_celle_qui_est_resolue(): void
    {
        $this->assertInstanceOf(SmsGateGateway::class, $this->gateway());
        $this->assertInstanceOf(TracksDeliveryStatus::class, $this->gateway());
        $this->assertFalse(app(SmsGatewayManager::class)->isSimulated());
    }

    public function test_un_envoi_respecte_le_contrat_de_l_api_smsgate(): void
    {
        Http::fake([
            self::BASE.'/messages' => Http::response([
                'id' => 'msg-123',
                'state' => 'Pending',
                'recipients' => [['phoneNumber' => '+22370001001', 'state' => 'Pending']],
            ], 202),
        ]);

        $result = $this->gateway()->send('+22370001001', 'Bonjour', 'Keneya');

        $this->assertTrue($result->successful);
        $this->assertSame('msg-123', $result->messageId);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === self::BASE.'/messages'
                && $request->method() === 'POST'
                // Authentification HTTP Basic
                && $request->hasHeader('Authorization',
                    'Basic '.base64_encode('utilisateur-test:secret-tres-confidentiel'))
                // Charge utile attendue par SMSGate
                && $body['message'] === 'Bonjour'
                && $body['phoneNumbers'] === ['+22370001001']
                && $body['withDeliveryReport'] === true;
        });
    }

    public function test_le_numero_de_sim_et_la_duree_de_validite_sont_transmis_si_configures(): void
    {
        $this->useSmsGate(['sim_number' => 2, 'ttl' => 3600]);

        Http::fake([self::BASE.'/*' => Http::response(['id' => 'x', 'state' => 'Pending'], 202)]);

        $this->gateway()->send('+22370001001', 'Bonjour');

        Http::assertSent(fn ($request) => $request->data()['simNumber'] === 2
            && $request->data()['ttl'] === 3600);
    }

    // -----------------------------------------------------------------
    // Correspondance des états, le cœur de l'exigence
    // -----------------------------------------------------------------

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function etatsSmsGate(): array
    {
        return [
            ['Pending', SmsResult::STATE_ACCEPTED],
            ['Processed', SmsResult::STATE_ACCEPTED],
            ['Sent', SmsResult::STATE_SENT],
            ['Delivered', SmsResult::STATE_DELIVERED],
            ['Failed', SmsResult::STATE_FAILED],
            ['Cancelled', SmsResult::STATE_CANCELLED],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('etatsSmsGate')]
    public function test_les_etats_smsgate_sont_traduits_fidelement(string $smsgate, string $attendu): void
    {
        Http::fake([
            self::BASE.'/messages' => Http::response([
                'id' => 'msg-1',
                'state' => $smsgate,
                'recipients' => [['phoneNumber' => '+22370001001', 'state' => $smsgate]],
            ], 202),
        ]);

        $this->assertSame($attendu, $this->gateway()->send('+22370001001', 'Test')->state);
    }

    public function test_un_message_accepte_n_est_jamais_presente_comme_envoye(): void
    {
        Queue::fake();

        Http::fake([
            self::BASE.'/messages' => Http::response([
                'id' => 'msg-abc',
                'state' => 'Pending',
                'recipients' => [['phoneNumber' => '+22370001001', 'state' => 'Pending']],
            ], 202),
        ]);

        $message = app(SmsService::class)->send('+22370001001', 'Rappel de rendez-vous.');
        app(SmsService::class)->deliver($message);

        $message->refresh();

        // Exigence d'exploitation : « Pending » côté SMSGate signifie que
        // l'appareil n'a même pas encore récupéré le message.
        $this->assertSame('accepted', $message->status);
        $this->assertNotSame('sent', $message->status);
        $this->assertNull($message->sent_at);
        $this->assertNotNull($message->accepted_at);
        $this->assertSame('msg-abc', $message->gateway_message_id);
    }

    // -----------------------------------------------------------------
    // Suivi d'acheminement
    // -----------------------------------------------------------------

    public function test_le_suivi_fait_progresser_le_message_jusqu_a_la_remise(): void
    {
        Queue::fake();

        Http::fake([
            self::BASE.'/messages' => Http::response(['id' => 'msg-9', 'state' => 'Pending'], 202),
        ]);

        $message = app(SmsService::class)->send('+22370001001', 'Test');
        app(SmsService::class)->deliver($message);
        $this->assertSame('accepted', $message->fresh()->status);

        Http::fake([
            self::BASE.'/messages/msg-9' => Http::response([
                'id' => 'msg-9',
                'state' => 'Delivered',
                'recipients' => [['phoneNumber' => '+22370001001', 'state' => 'Delivered']],
            ]),
        ]);

        app(SmsService::class)->refreshStatus($message);

        $message->refresh();
        $this->assertSame('delivered', $message->status);
        $this->assertNotNull($message->delivered_at);
        $this->assertNotNull($message->status_checked_at);
    }

    public function test_la_commande_de_suivi_met_a_jour_les_messages_en_transit(): void
    {
        $message = SmsMessage::create([
            'reference' => 'SMS-SUIVI0001',
            'recipient' => '+22370001001',
            'body' => 'Test',
            'status' => 'accepted',
            'gateway' => 'smsgate',
            'gateway_message_id' => 'msg-42',
            'accepted_at' => now(),
        ]);

        Http::fake([
            self::BASE.'/messages/msg-42' => Http::response([
                'id' => 'msg-42',
                'state' => 'Sent',
                'recipients' => [['phoneNumber' => '+22370001001', 'state' => 'Sent']],
            ]),
        ]);

        $this->artisan('keneya:sms:refresh')->assertSuccessful();

        $this->assertSame('sent', $message->fresh()->status);
    }

    public function test_une_panne_reseau_pendant_le_suivi_ne_declare_pas_le_message_en_echec(): void
    {
        $message = SmsMessage::create([
            'reference' => 'SMS-SUIVI0002',
            'recipient' => '+22370001001',
            'body' => 'Test',
            'status' => 'accepted',
            'gateway' => 'smsgate',
            'gateway_message_id' => 'msg-77',
            'accepted_at' => now(),
        ]);

        // La passerelle est injoignable : on n'a rien appris du sort du message.
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Connexion refusée'));

        app(SmsService::class)->refreshStatus($message);

        $this->assertSame('accepted', $message->fresh()->status);
        $this->assertNotNull($message->fresh()->status_checked_at);
    }

    // -----------------------------------------------------------------
    // Erreurs et configuration
    // -----------------------------------------------------------------

    public function test_une_erreur_d_authentification_est_signalee_clairement(): void
    {
        Http::fake([self::BASE.'/*' => Http::response(['message' => 'unauthorized'], 401)]);

        $result = $this->gateway()->send('+22370001001', 'Test');

        $this->assertFalse($result->successful);
        $this->assertStringContainsString('refusé les identifiants', $result->error);
    }

    public function test_une_passerelle_non_configuree_echoue_sans_appel_reseau(): void
    {
        $this->useSmsGate(['username' => null, 'password' => null, 'token' => null]);
        Http::fake();

        $result = $this->gateway()->send('+22370001001', 'Test');

        $this->assertFalse($result->successful);
        $this->assertStringContainsString('SMSGATE_USERNAME', $result->error);
        Http::assertNothingSent();
    }

    public function test_aucun_secret_ne_fuite_dans_les_messages_d_erreur(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException(
            'Échec vers https://utilisateur-test:secret-tres-confidentiel@sms.example.test'
        ));

        $result = $this->gateway()->send('+22370001001', 'Test');

        $this->assertFalse($result->successful);
        $this->assertStringNotContainsString('secret-tres-confidentiel', $result->error);
    }

    public function test_le_mot_de_passe_n_apparait_pas_dans_l_historique_persiste(): void
    {
        Queue::fake();
        Http::fake([self::BASE.'/*' => Http::response(['message' => 'boom'], 500)]);

        $message = app(SmsService::class)->send('+22370001001', 'Test');
        app(SmsService::class)->deliver($message);

        $stored = json_encode($message->fresh()->toArray(), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('secret-tres-confidentiel', (string) $stored);
    }
}
