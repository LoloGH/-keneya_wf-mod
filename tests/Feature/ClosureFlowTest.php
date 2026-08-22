<?php

namespace Tests\Feature;

use App\Actions\CloseReferral;
use App\Actions\CloseVisit;
use App\Actions\CompleteReferral;
use App\Actions\SendReferral;
use App\Livewire\Service\OutgoingReferrals;
use App\Livewire\Service\ServiceQueue;
use App\Models\Doctor;
use App\Models\PatientHistory;
use App\Models\Referral;
use App\Models\Service;
use App\Models\Visit;
use App\Services\SmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Points 1 et 2 de l'addendum v2 : cloture d'un renvoi (la boucle du
 * prescripteur) et cloture d'un dossier (l'episode de soins). Les deux sont
 * distincts et ne se declenchent pas au meme endroit.
 */
class ClosureFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(SmsGateway::class)->shouldReceive('send')->andReturnTrue();
    }

    // ---------------------------------------------------------------- Renvoi

    public function test_le_prescripteur_cloture_un_renvoi_dont_le_resultat_est_arrive(): void
    {
        [$referral, $prescriber] = $this->completedReferral();

        $closed = app(CloseReferral::class)->execute($referral, $prescriber);

        $this->assertSame(Referral::STATUS_CLOSED, $closed->status);
        $this->assertSame($prescriber->getKey(), $closed->closed_by_doctor_id);
        $this->assertNotNull($closed->closed_at);

        $this->assertDatabaseHas('patient_history', [
            'referral_id' => $referral->getKey(),
            'type' => PatientHistory::TYPE_REFERRAL_CLOSED,
        ]);
    }

    public function test_un_renvoi_sans_resultat_ne_peut_pas_etre_cloture(): void
    {
        $source = Service::factory()->create();
        $destination = Service::factory()->plateauTechnique()->create();
        $prescriber = $this->makeDoctor($source);

        $referral = app(SendReferral::class)->execute(
            visit: $this->makeVisit($source),
            fromDoctor: $prescriber,
            toService: $destination,
            instructions: 'Analyse demandee.',
        );

        $this->expectException(InvalidArgumentException::class);

        app(CloseReferral::class)->execute($referral, $prescriber);
    }

    public function test_seul_le_medecin_a_l_origine_du_renvoi_peut_le_cloturer(): void
    {
        [$referral] = $this->completedReferral();
        $autre = $this->makeDoctor(Service::factory()->create());

        $this->expectException(InvalidArgumentException::class);

        app(CloseReferral::class)->execute($referral, $autre);
    }

    public function test_un_renvoi_cloture_quitte_le_panneau_des_resultats_recus(): void
    {
        [$referral, $prescriber] = $this->completedReferral();

        $panel = Livewire::actingAs($prescriber->user)
            ->test(OutgoingReferrals::class, ['serviceId' => $prescriber->service_id]);

        $panel->assertSee('Resultat de l\'examen.');

        $panel->call('closeReferral', $referral->getKey())->assertHasNoErrors();

        // Disparu du panneau...
        Livewire::actingAs($prescriber->user)
            ->test(OutgoingReferrals::class, ['serviceId' => $prescriber->service_id])
            ->assertDontSee('Resultat de l\'examen.');

        // ...mais toujours dans l'historique du patient.
        $this->assertDatabaseHas('patient_history', [
            'referral_id' => $referral->getKey(),
            'type' => PatientHistory::TYPE_REFERRAL_RESULT,
        ]);
    }

    // --------------------------------------------------------------- Dossier

    public function test_le_medecin_cloture_le_dossier_d_un_patient_appele(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        Livewire::actingAs($doctor->user)
            ->test(ServiceQueue::class, ['serviceId' => $service->getKey()])
            ->call('closeVisit', $visit->getKey())
            ->assertHasNoErrors();

        $visit->refresh();

        $this->assertSame(Visit::STATUS_CLOSED, $visit->status);
        $this->assertNotNull($visit->closed_at);

        $this->assertDatabaseHas('patient_history', [
            'visit_id' => $visit->getKey(),
            'type' => PatientHistory::TYPE_DOSSIER_CLOSED,
        ]);
    }

    public function test_un_dossier_en_attente_de_resultat_ne_peut_pas_etre_cloture(): void
    {
        $source = Service::factory()->create();
        $destination = Service::factory()->plateauTechnique()->create();
        $doctor = $this->makeDoctor($source);
        $visit = $this->makeVisit($source, ['status' => Visit::STATUS_CALLED]);

        app(SendReferral::class)->execute($visit, $doctor, $destination, 'Analyse demandee.');

        // Le renvoi a deplace la visite ; on la ramene dans le service source
        // et on la remet « appelee » pour tester le seul garde-fou du renvoi
        // encore en attente.
        $visit->forceFill([
            'service_id' => $source->getKey(),
            'status' => Visit::STATUS_CALLED,
        ])->save();

        $this->expectException(InvalidArgumentException::class);

        app(CloseVisit::class)->execute($visit->refresh(), $doctor);
    }

    public function test_un_patient_en_attente_ne_peut_pas_etre_cloture(): void
    {
        $service = Service::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        app(CloseVisit::class)->execute(
            $this->makeVisit($service, ['status' => Visit::STATUS_WAITING]),
            $this->makeDoctor($service),
        );
    }

    public function test_un_dossier_cloture_sort_de_la_file_active(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);

        $this->makeVisit($service, ['token' => 1, 'status' => Visit::STATUS_CLOSED]);
        $attente = $this->makeVisit($service, ['token' => 2]);

        Livewire::actingAs($doctor->user)
            ->test(ServiceQueue::class, ['serviceId' => $service->getKey()])
            ->call('callNext')
            ->assertHasNoErrors();

        // « Appeler le suivant » saute le dossier cloture.
        $this->assertSame(Visit::STATUS_CALLED, $attente->refresh()->status);
    }

    public function test_un_dossier_cloture_ne_peut_plus_etre_renvoye(): void
    {
        $service = Service::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        app(SendReferral::class)->execute(
            visit: $this->makeVisit($service, ['status' => Visit::STATUS_CLOSED]),
            fromDoctor: $this->makeDoctor($service),
            toService: Service::factory()->create(),
            instructions: 'Instructions.',
        );
    }

    /**
     * @return array{0: Referral, 1: Doctor}
     */
    private function completedReferral(): array
    {
        $source = Service::factory()->create();
        $destination = Service::factory()->plateauTechnique()->create();

        $prescriber = $this->makeDoctor($source);
        $technicien = $this->makeDoctor($destination);

        $referral = app(SendReferral::class)->execute(
            visit: $this->makeVisit($source),
            fromDoctor: $prescriber,
            toService: $destination,
            instructions: 'Analyse demandee.',
        );

        app(CompleteReferral::class)->execute($referral, $technicien, 'Resultat de l\'examen.');

        return [$referral->refresh(), $prescriber];
    }
}
