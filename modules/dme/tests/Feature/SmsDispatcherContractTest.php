<?php

declare(strict_types=1);

namespace Keneya\Dme\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Keneya\Dme\Contracts\SmsDispatcherContract;
use Keneya\Dme\Models\Appointment;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\SmsMessage;
use Keneya\Dme\Services\Notifications\NotificationService;
use Keneya\Dme\Sms\LogSmsDispatcher;
use Keneya\Dme\Sms\Pipeline\QueuedSmsDispatcher;
use Keneya\Dme\Sms\SmsContext;
use Keneya\Dme\Support\Rbac;
use Keneya\Dme\Tests\TestCase;

/**
 * Le module dépend d'un contrat d'envoi, jamais d'une implémentation.
 *
 * Ces tests jouent le rôle que tiendra Keneya Workflow : ils substituent
 * leur propre implémentation au contrat et vérifient que le module
 * l'utilise, sans qu'aucune ligne de code métier ait changé.
 */
class SmsDispatcherContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    /**
     * Implémentation d'hôte factice : elle retient ce qu'on lui remet.
     */
    private function fakeDispatcher(): SmsDispatcherContract
    {
        return new class implements SmsDispatcherContract
        {
            /** @var list<array{to: string, message: string, context: ?string}> */
            public array $envois = [];

            public function dispatch(string $to, string $message, ?string $context = null): void
            {
                $this->envois[] = ['to' => $to, 'message' => $message, 'context' => $context];
            }
        };
    }

    public function test_l_implementation_par_defaut_est_la_file_interne_du_module(): void
    {
        $this->assertInstanceOf(QueuedSmsDispatcher::class, app(SmsDispatcherContract::class));
    }

    public function test_la_configuration_permet_de_basculer_sur_la_journalisation(): void
    {
        config()->set('dme.sms.dispatcher', 'log');
        app()->forgetInstance(SmsDispatcherContract::class);

        $this->assertInstanceOf(LogSmsDispatcher::class, app(SmsDispatcherContract::class));
    }

    public function test_un_evenement_metier_passe_par_le_contrat_fourni_par_l_hote(): void
    {
        $dispatcher = $this->fakeDispatcher();
        $this->app->instance(SmsDispatcherContract::class, $dispatcher);

        $patient = Patient::factory()->create(['phone' => '+22370001001']);
        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->getKey(),
            'doctor_id' => $this->userWithRole(Rbac::ROLE_DOCTOR)->getKey(),
        ]);

        app(NotificationService::class)->appointmentScheduled($appointment);

        $this->assertCount(1, $dispatcher->envois);
        $this->assertSame('+22370001001', $dispatcher->envois[0]['to']);
        $this->assertStringContainsString($patient->fullName(), $dispatcher->envois[0]['message']);

        // Aucun message n'a été persisté : l'hôte a pris la main sur
        // l'envoi, la file interne du module n'a pas été sollicitée.
        $this->assertSame(0, SmsMessage::count());
    }

    public function test_le_contexte_transmis_designe_le_patient_et_l_acte(): void
    {
        $dispatcher = $this->fakeDispatcher();
        $this->app->instance(SmsDispatcherContract::class, $dispatcher);

        $patient = Patient::factory()->create(['phone' => '+22370001001']);
        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->getKey(),
            'doctor_id' => $this->userWithRole(Rbac::ROLE_DOCTOR)->getKey(),
        ]);

        app(NotificationService::class)->appointmentScheduled($appointment);

        $context = SmsContext::parse($dispatcher->envois[0]['context']);

        $this->assertSame((int) $patient->getKey(), $context->patientId);
        $this->assertSame(Appointment::class, $context->subjectType);
        $this->assertSame((int) $appointment->getKey(), $context->subjectId);
        $this->assertSame('appointment_scheduled', $context->templateKey);
    }

    public function test_la_file_interne_reconstitue_l_historique_a_partir_du_contexte(): void
    {
        $patient = Patient::factory()->create(['phone' => '+22370001001']);
        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->getKey(),
            'doctor_id' => $this->userWithRole(Rbac::ROLE_DOCTOR)->getKey(),
        ]);

        app(NotificationService::class)->appointmentScheduled($appointment);

        $message = SmsMessage::firstOrFail();

        $this->assertSame((int) $patient->getKey(), (int) $message->patient_id);
        $this->assertSame(Appointment::class, $message->context_type);
        $this->assertSame((int) $appointment->getKey(), (int) $message->context_id);
        $this->assertNotNull($message->sms_template_id);
    }

    public function test_une_panne_de_l_envoi_n_interrompt_pas_l_acte_medical(): void
    {
        $this->app->instance(SmsDispatcherContract::class, new class implements SmsDispatcherContract
        {
            public function dispatch(string $to, string $message, ?string $context = null): void
            {
                throw new \RuntimeException('Passerelle injoignable');
            }
        });

        $patient = Patient::factory()->create(['phone' => '+22370001001']);
        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->getKey(),
            'doctor_id' => $this->userWithRole(Rbac::ROLE_DOCTOR)->getKey(),
        ]);

        // Aucune exception ne doit remonter jusqu'à l'appelant.
        app(NotificationService::class)->appointmentScheduled($appointment);

        $this->assertTrue(true);
    }

    public function test_l_implementation_de_journalisation_n_emet_rien(): void
    {
        $dispatcher = app(LogSmsDispatcher::class);

        $dispatcher->dispatch('70 00 10 01', 'Message de test', 'patient=1');

        $this->assertSame(0, SmsMessage::count());
    }

    public function test_l_implementation_de_journalisation_refuse_un_numero_inexploitable(): void
    {
        $this->expectException(\RuntimeException::class);

        app(LogSmsDispatcher::class)->dispatch('   ', 'Message de test');
    }
}
