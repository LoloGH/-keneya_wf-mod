<?php

declare(strict_types=1);

namespace Keneya\Dme\Tests\Feature;

use Keneya\Dme\Sms\Pipeline\SendSmsMessage;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\SmsMessage;
use Keneya\Dme\Support\PhoneNumber;
use Keneya\Dme\Sms\Pipeline\SmsGateway;
use Keneya\Dme\Sms\Pipeline\SmsGatewayManager;
use Keneya\Dme\Sms\Pipeline\SmsResult;
use Keneya\Dme\Sms\Pipeline\SmsService;
use Keneya\Dme\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Keneya\Dme\Tests\TestCase;

/**
 * Service SMS transversal (§35, §53).
 */
class SmsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    public function test_les_numeros_sont_normalises_au_format_e164(): void
    {
        $this->assertSame('+22370001001', PhoneNumber::normalize('70 00 10 01'));
        $this->assertSame('+22370001001', PhoneNumber::normalize('+223 70 00 10 01'));
        $this->assertSame('+22370001001', PhoneNumber::normalize('0022370001001'));
        $this->assertNull(PhoneNumber::normalize(''));
        $this->assertNull(PhoneNumber::normalize(null));
    }

    public function test_un_numero_trop_court_n_est_pas_envoyable(): void
    {
        $this->assertFalse(PhoneNumber::isSendable('123'));
        $this->assertTrue(PhoneNumber::isSendable('+22370001001'));
    }

    public function test_un_message_est_persiste_puis_mis_en_file(): void
    {
        Queue::fake();

        $message = app(SmsService::class)->send('70 00 10 01', 'Message de test.');

        $this->assertSame('+22370001001', $message->recipient);
        $this->assertSame('queued', $message->status);

        Queue::assertPushed(SendSmsMessage::class,
            fn (SendSmsMessage $job) => $job->smsMessageId === $message->id);
    }

    public function test_un_destinataire_invalide_est_refuse(): void
    {
        $this->expectException(RuntimeException::class);

        app(SmsService::class)->send('', 'Message de test.');
    }

    public function test_un_modele_produit_le_texte_attendu(): void
    {
        Queue::fake();

        $message = app(SmsService::class)->sendTemplate('prescription_ready', '+22370001001', [
            'patient_name' => 'Mamadou Traoré',
            'reference' => 'ORD-2026-000001',
        ]);

        $this->assertNotNull($message);
        $this->assertSame(
            'Keneya : Votre ordonnance a été enregistrée. Référence : ORD-2026-000001.',
            $message->body,
        );
    }

    public function test_un_modele_inconnu_n_interrompt_pas_le_flux_metier(): void
    {
        // Un modèle absent ne doit jamais faire échouer l'acte médical
        // qui l'a déclenché : le SMS est simplement omis.
        $this->assertNull(
            app(SmsService::class)->sendTemplate('modele_inexistant', '+22370001001')
        );
    }

    public function test_un_envoi_reussi_met_le_message_a_l_etat_envoye(): void
    {
        // La file est simulée afin de piloter explicitement l'envoi :
        // sur la connexion « sync », le job partirait dès la mise en file.
        Queue::fake();

        $message = app(SmsService::class)->send('+22370001001', 'Message de test.');

        $result = app(SmsService::class)->deliver($message);

        $this->assertTrue($result->successful);
        $message->refresh();
        $this->assertSame('sent', $message->status);
        $this->assertSame(1, $message->attempts);
        $this->assertNotNull($message->sent_at);
    }

    public function test_un_echec_de_passerelle_est_trace_et_rejouable(): void
    {
        Queue::fake();

        // Passerelle en échec systématique.
        app(SmsGatewayManager::class)->extend('array', new class implements SmsGateway
        {
            public function send(string $recipient, string $body, ?string $sender = null): SmsResult
            {
                return SmsResult::failure($this->name(), 'Passerelle indisponible.');
            }

            public function name(): string
            {
                return 'array';
            }
        });

        $message = app(SmsService::class)->send('+22370001001', 'Message de test.');
        app(SmsService::class)->deliver($message);

        $message->refresh();
        $this->assertSame('failed', $message->status);
        $this->assertSame('Passerelle indisponible.', $message->error_message);
        $this->assertTrue($message->isRetryable());
    }

    public function test_un_message_ayant_epuise_ses_tentatives_n_est_plus_rejouable(): void
    {
        $message = SmsMessage::create([
            'reference' => 'SMS-TEST00001',
            'recipient' => '+22370001001',
            'body' => 'Message de test.',
            'status' => 'failed',
            'attempts' => config('dme.sms.retry.max_attempts'),
        ]);

        $this->assertFalse($message->isRetryable());

        $this->expectException(RuntimeException::class);
        app(SmsService::class)->retry($message);
    }

    public function test_le_service_sms_ne_depend_d_aucun_modele_medical(): void
    {
        Queue::fake();

        // Un contexte métier arbitraire est accepté sans contrainte de clé
        // étrangère : c'est la condition de l'extraction du service en phase 2.
        $message = app(SmsService::class)->send('+22370001001', 'Message de test.', [
            'patient_id' => 999999,
            'context_type' => 'Keneya\\Dme\\Models\\Inexistant',
            'context_id' => 42,
        ]);

        $this->assertSame(999999, $message->patient_id);
        $this->assertDatabaseHas('dme_sms_messages', ['id' => $message->id]);
    }

    public function test_un_envoi_manuel_depuis_l_interface_est_mis_en_file(): void
    {
        Queue::fake();

        $patient = Patient::factory()->create();

        $this->actingAs($this->userWithRole(Rbac::ROLE_RECEPTION))
            ->post(route('dme.sms.store'), [
                'recipient' => '+22370001001',
                'body' => 'Rappel de rendez-vous.',
                'patient_id' => $patient->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('dme_sms_messages', [
            'recipient' => '+22370001001',
            'patient_id' => $patient->id,
            'status' => 'queued',
        ]);
    }

    public function test_un_role_sans_permission_ne_peut_pas_envoyer_de_sms(): void
    {
        $this->actingAs($this->userWithRole(Rbac::ROLE_LAB))
            ->post(route('dme.sms.store'), [
                'recipient' => '+22370001001',
                'body' => 'Message non autorisé.',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('dme_sms_messages', 0);
    }
}
