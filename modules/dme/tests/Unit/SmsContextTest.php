<?php

declare(strict_types=1);

namespace Keneya\Dme\Tests\Unit;

use Illuminate\Support\Carbon;
use Keneya\Dme\Sms\SmsContext;
use Keneya\Dme\Tests\TestCase;

/**
 * Encodage du contexte d'un SMS.
 *
 * Le contrat d'envoi ne transporte qu'une chaîne : c'est elle qui permet
 * au module de relier un message à son patient et à l'acte qui l'a
 * déclenché, sans que le code métier connaisse l'implémentation d'envoi.
 */
class SmsContextTest extends TestCase
{
    public function test_un_contexte_vide_ne_produit_aucune_chaine(): void
    {
        $this->assertNull((new SmsContext)->toContextString());
    }

    public function test_l_encodage_puis_la_relecture_conservent_les_informations(): void
    {
        $context = new SmsContext(
            patientId: 12,
            subjectType: 'Keneya\Dme\Models\Appointment',
            subjectId: 34,
            templateKey: 'appointment_reminder',
            sendAt: Carbon::parse('2026-09-08 09:30:00'),
        );

        $relu = SmsContext::parse((string) $context);

        $this->assertSame(12, $relu->patientId);
        $this->assertSame('Keneya\Dme\Models\Appointment', $relu->subjectType);
        $this->assertSame(34, $relu->subjectId);
        $this->assertSame('appointment_reminder', $relu->templateKey);
        $this->assertTrue($relu->sendAt?->equalTo(Carbon::parse('2026-09-08 09:30:00')));
    }

    public function test_les_antislashs_d_un_nom_de_classe_survivent_a_l_encodage(): void
    {
        $encoded = (string) new SmsContext(subjectType: 'Keneya\Dme\Models\LabOrder');

        $this->assertStringNotContainsString('\\', $encoded);
        $this->assertSame('Keneya\Dme\Models\LabOrder', SmsContext::parse($encoded)->subjectType);
    }

    public function test_une_chaine_inconnue_est_acceptee_sans_erreur(): void
    {
        // Une implémentation hôte peut passer ce qu'elle veut : le module
        // ne doit jamais échouer sur un contexte qu'il ne comprend pas.
        $context = SmsContext::parse('un texte libre venu d\'ailleurs');

        $this->assertNull($context->patientId);
        $this->assertNull($context->subjectType);
        $this->assertNull($context->sendAt);
    }

    public function test_une_date_illisible_est_ignoree_plutot_que_fatale(): void
    {
        $this->assertNull(SmsContext::parse('send_at=pas-une-date')->sendAt);
    }
}
