<?php

namespace Tests\Feature;

use App\Actions\CompleteReferral;
use App\Actions\RegisterPatient;
use App\Actions\SendReferral;
use App\Models\Doctor;
use App\Models\PatientHistory;
use App\Models\Referral;
use App\Models\Service;
use App\Models\Visit;
use App\Services\SmsGateway;
use ArrayObject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Flux de renvoi complet : le dossier traverse les services sans jamais etre
 * duplique, et chaque etape laisse une trace dans l'historique.
 */
class ReferralFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_flux_de_renvoi_complet(): void
    {
        $sms = $this->mockSms();
        $sms->shouldReceive('send')->andReturnTrue();

        $medecineGenerale = Service::factory()->create(['name' => 'Medecine Generale']);
        $echographie = Service::factory()->plateauTechnique()->create(['name' => 'Echographie']);

        $prescriber = $this->makeDoctor($medecineGenerale, '76000001');
        $radiologue = $this->makeDoctor($echographie);

        $visit = app(RegisterPatient::class)->execute([
            'name' => 'Sekou Diarra',
            'age' => 41,
            'gender' => 'Homme',
            'mobile' => '76445566',
            'service_id' => $medecineGenerale->getKey(),
        ]);

        $patient = $visit->patient;

        // Un passage deja en file dans le service destinataire, pour verifier
        // que le nouveau ticket suit bien la file d'Echographie.
        $this->makeVisit($echographie, ['token' => 7]);

        $referral = app(SendReferral::class)->execute(
            visit: $visit,
            fromDoctor: $prescriber,
            toService: $echographie,
            instructions: 'Echographie abdominale a jeun.',
        );

        $visit->refresh();

        $this->assertSame(Referral::STATUS_PENDING, $referral->status);
        $this->assertSame($medecineGenerale->getKey(), $referral->from_service_id);
        $this->assertSame($echographie->getKey(), $referral->to_service_id);
        $this->assertSame($prescriber->getKey(), $referral->from_doctor_id);

        // C'est la visite qui se deplace : meme episode, nouveau service,
        // nouveau ticket, et toujours un seul dossier.
        $this->assertSame($echographie->getKey(), $visit->service_id);
        $this->assertSame(8, $visit->token);
        $this->assertSame(Visit::STATUS_WAITING, $visit->status);
        $this->assertSame($visit->getKey(), $referral->visit_id);
        $this->assertSame(1, $visit->patient->visits()->count());

        $this->assertDatabaseHas('patient_history', [
            'patient_id' => $patient->getKey(),
            'type' => PatientHistory::TYPE_REFERRAL_SENT,
            'referral_id' => $referral->getKey(),
        ]);

        $completed = app(CompleteReferral::class)->execute(
            referral: $referral,
            completedBy: $radiologue,
            resultText: 'Pas d\'anomalie decelee.',
        );

        $this->assertSame(Referral::STATUS_DONE, $completed->status);
        $this->assertSame('Pas d\'anomalie decelee.', $completed->result_text);
        $this->assertSame($radiologue->getKey(), $completed->completed_by_doctor_id);
        $this->assertNotNull($completed->completed_at);

        $this->assertDatabaseHas('patient_history', [
            'patient_id' => $patient->getKey(),
            'type' => PatientHistory::TYPE_REFERRAL_RESULT,
            'referral_id' => $referral->getKey(),
        ]);

        // Historique complet et ordonne : enregistrement -> renvoi -> resultat.
        $this->assertSame(
            [
                PatientHistory::TYPE_REGISTRATION,
                PatientHistory::TYPE_REFERRAL_SENT,
                PatientHistory::TYPE_REFERRAL_RESULT,
            ],
            PatientHistory::where('patient_id', $patient->getKey())
                ->orderBy('id')
                ->pluck('type')
                ->all(),
        );
    }

    public function test_le_patient_est_prevenu_par_sms_du_renvoi(): void
    {
        $sent = $this->captureSms();

        $source = Service::factory()->create();
        $echographie = Service::factory()->plateauTechnique()->create(['name' => 'Echographie']);

        $visit = app(RegisterPatient::class)->execute([
            'name' => 'Sekou Diarra',
            'age' => 41,
            'gender' => 'Homme',
            'mobile' => '76445566',
            'service_id' => $source->getKey(),
        ]);

        app(SendReferral::class)->execute(
            visit: $visit,
            fromDoctor: $this->makeDoctor($source),
            toService: $echographie,
            instructions: 'Echographie abdominale.',
        );

        // Un SMS a l'enregistrement, puis un second annoncant le renvoi.
        $this->assertCount(2, $sent);
        $this->assertSame(['76445566', '76445566'], array_column($sent->getArrayCopy(), 'to'));
        $this->assertStringContainsString('Echographie', $sent[1]['text']);
    }

    public function test_le_prescripteur_est_prevenu_si_son_numero_est_renseigne(): void
    {
        $sent = $this->captureSms();

        [$referral, $radiologue] = $this->pendingReferral(prescriberPhone: '76000001');

        $sent->exchangeArray([]);

        app(CompleteReferral::class)->execute($referral, $radiologue, 'Resultat normal.');

        $this->assertCount(1, $sent);
        $this->assertSame('76000001', $sent[0]['to']);
        $this->assertStringContainsString('resultat', $sent[0]['text']);
    }

    public function test_un_prescripteur_sans_numero_n_est_pas_notifie_par_sms(): void
    {
        $sent = $this->captureSms();

        [$referral, $radiologue] = $this->pendingReferral(prescriberPhone: null);

        $sent->exchangeArray([]);

        $completed = app(CompleteReferral::class)->execute($referral, $radiologue, 'Resultat normal.');

        // Aucun SMS possible, mais le resultat reste consultable dans le
        // panneau « Resultats recus » du prescripteur.
        $this->assertCount(0, $sent);
        $this->assertSame(Referral::STATUS_DONE, $completed->status);
    }

    public function test_un_renvoi_vers_le_meme_service_est_refuse(): void
    {
        $this->mockSms()->shouldReceive('send')->andReturnTrue();

        $service = Service::factory()->create();
        $visit = $this->makeVisit($service);

        $this->expectException(InvalidArgumentException::class);

        app(SendReferral::class)->execute(
            visit: $visit,
            fromDoctor: $this->makeDoctor($service),
            toService: $service,
            instructions: 'Instructions.',
        );
    }

    public function test_seul_un_praticien_du_service_destinataire_peut_saisir_le_resultat(): void
    {
        $this->mockSms()->shouldReceive('send')->andReturnTrue();

        [$referral] = $this->pendingReferral();
        $etranger = $this->makeDoctor(Service::factory()->create());

        $this->expectException(InvalidArgumentException::class);

        app(CompleteReferral::class)->execute($referral, $etranger, 'Resultat.');
    }

    public function test_un_renvoi_deja_traite_ne_peut_pas_etre_repris(): void
    {
        $this->mockSms()->shouldReceive('send')->andReturnTrue();

        [$referral, $radiologue] = $this->pendingReferral();

        app(CompleteReferral::class)->execute($referral, $radiologue, 'Premier resultat.');

        $this->expectException(InvalidArgumentException::class);

        app(CompleteReferral::class)->execute($referral->refresh(), $radiologue, 'Second resultat.');
    }

    public function test_l_historique_est_append_only(): void
    {
        $this->mockSms()->shouldReceive('send')->andReturnTrue();

        $service = Service::factory()->create();
        app(RegisterPatient::class)->execute([
            'name' => 'Aminata Sow',
            'age' => 29,
            'gender' => 'Femme',
            'mobile' => '76778899',
            'service_id' => $service->getKey(),
        ]);

        $entry = PatientHistory::firstOrFail();

        $this->expectException(\RuntimeException::class);

        $entry->update(['description' => 'Description reecrite']);
    }

    /**
     * @return array{0: Referral, 1: Doctor}
     */
    private function pendingReferral(?string $prescriberPhone = null): array
    {
        $source = Service::factory()->create();
        $destination = Service::factory()->plateauTechnique()->create();

        $visit = $this->makeVisit($source);

        $referral = app(SendReferral::class)->execute(
            visit: $visit,
            fromDoctor: $this->makeDoctor($source, $prescriberPhone),
            toService: $destination,
            instructions: 'Analyse demandee.',
        );

        return [$referral, $this->makeDoctor($destination)];
    }

    private function mockSms(): MockInterface
    {
        return $this->mock(SmsGateway::class);
    }

    /**
     * Remplace la passerelle SMS par un double qui enregistre les envois.
     *
     * @return ArrayObject<int, array{to: string, text: string}>
     */
    private function captureSms(): ArrayObject
    {
        /** @var ArrayObject<int, array{to: string, text: string}> $sent */
        $sent = new ArrayObject;

        $this->mockSms()
            ->shouldReceive('send')
            ->andReturnUsing(function (string $to, string $text) use ($sent): bool {
                $sent[] = ['to' => $to, 'text' => $text];

                return true;
            });

        return $sent;
    }
}
