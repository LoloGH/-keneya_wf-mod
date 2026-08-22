<?php

namespace Tests\Feature;

use App\Actions\SendReferral;
use App\Livewire\Service\IncomingReferrals;
use App\Livewire\Service\PatientRecordPanel;
use App\Livewire\Service\ServiceQueue;
use App\Models\Doctor;
use App\Models\PatientHistory;
use App\Models\Referral;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Services\SmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Le medecin fait tout depuis /service : appel, renvoi, resultat, dossier.
 */
class ServiceInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(SmsGateway::class)->shouldReceive('send')->andReturnTrue();
    }

    public function test_le_medecin_appelle_le_patient_suivant(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);

        $premier = $this->makeVisit($service, ['token' => 1]);
        $second = $this->makeVisit($service, ['token' => 2]);

        Livewire::actingAs($doctor->user)
            ->test(ServiceQueue::class, ['serviceId' => $service->getKey()])
            ->call('callNext')
            ->assertHasNoErrors();

        $this->assertSame(Visit::STATUS_CALLED, $premier->refresh()->status);
        $this->assertSame(Visit::STATUS_WAITING, $second->refresh()->status);

        $this->assertDatabaseHas('patient_history', [
            'visit_id' => $premier->getKey(),
            'type' => PatientHistory::TYPE_CONSULTATION,
        ]);
    }

    public function test_le_medecin_envoie_un_patient_vers_un_autre_service(): void
    {
        $source = Service::factory()->create();
        $destination = Service::factory()->plateauTechnique()->create();
        $doctor = $this->makeDoctor($source);
        $visit = $this->makeVisit($source, ['token' => 3]);

        Livewire::actingAs($doctor->user)
            ->test(ServiceQueue::class, ['serviceId' => $source->getKey()])
            ->call('startReferral', $visit->getKey())
            ->set('toServiceId', $destination->getKey())
            ->set('instructions', 'Numeration formule sanguine.')
            ->call('sendReferral')
            ->assertHasNoErrors();

        $this->assertSame($destination->getKey(), $visit->refresh()->service_id);
        $this->assertDatabaseHas('referrals', [
            'visit_id' => $visit->getKey(),
            'to_service_id' => $destination->getKey(),
            'status' => Referral::STATUS_PENDING,
        ]);
    }

    public function test_un_renvoi_vers_le_service_courant_est_rejete_par_le_formulaire(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);
        $visit = $this->makeVisit($service);

        Livewire::actingAs($doctor->user)
            ->test(ServiceQueue::class, ['serviceId' => $service->getKey()])
            ->call('startReferral', $visit->getKey())
            ->set('toServiceId', $service->getKey())
            ->set('instructions', 'Instructions valides.')
            ->call('sendReferral')
            ->assertHasErrors('toServiceId');
    }

    public function test_le_praticien_destinataire_saisit_le_resultat(): void
    {
        $source = Service::factory()->create();
        $destination = Service::factory()->plateauTechnique()->create();
        $prescriber = $this->makeDoctor($source, '76000009');
        $technicien = $this->makeDoctor($destination);
        $visit = $this->makeVisit($source);

        $referral = app(SendReferral::class)->execute(
            visit: $visit,
            fromDoctor: $prescriber,
            toService: $destination,
            instructions: 'Radiographie du thorax.',
        );

        Livewire::actingAs($technicien->user)
            ->test(IncomingReferrals::class, ['serviceId' => $destination->getKey()])
            ->call('startAnswer', $referral->getKey())
            ->set('resultText', 'Champs pulmonaires libres.')
            ->call('submitResult')
            ->assertHasNoErrors();

        $referral->refresh();

        $this->assertSame(Referral::STATUS_DONE, $referral->status);
        $this->assertSame('Champs pulmonaires libres.', $referral->result_text);
        $this->assertSame($technicien->getKey(), $referral->completed_by_doctor_id);
    }

    public function test_un_medecin_ne_peut_pas_ouvrir_la_file_d_un_service_qui_n_est_pas_le_sien(): void
    {
        $doctor = $this->makeDoctor(Service::factory()->create());
        $autre = Service::factory()->create();

        Livewire::actingAs($doctor->user)
            ->test(ServiceQueue::class, ['serviceId' => $autre->getKey()])
            ->assertForbidden();
    }

    public function test_le_dossier_s_ouvre_dans_l_interface_service(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);
        $visit = $this->makeVisit($service);
        $patient = $visit->patient;

        // La file demande l'ouverture du dossier...
        Livewire::actingAs($doctor->user)
            ->test(ServiceQueue::class, ['serviceId' => $service->getKey()])
            ->call('showRecord', $patient->getKey())
            ->assertDispatched('afficher-dossier');

        // ...et le panneau lateral l'affiche, sans changer de page.
        Livewire::actingAs($doctor->user)
            ->test(PatientRecordPanel::class)
            ->call('open', $patient->getKey())
            ->assertSee($patient->patient_code)
            ->assertSee($patient->name);
    }

    public function test_un_medecin_multi_service_bascule_entre_ses_seuls_services(): void
    {
        $premier = Service::factory()->create();
        $second = Service::factory()->create();
        $etranger = Service::factory()->create();

        $doctor = $this->makeDoctor($premier);
        $this->makeDoctorFor($doctor->user, $second);

        $component = Livewire::actingAs($doctor->user)
            ->test(ServiceQueue::class, ['serviceId' => $premier->getKey()]);

        $component->call('handleServiceChange', $second->getKey())
            ->assertSet('serviceId', $second->getKey());

        // Un service auquel il n'est pas rattache reste inaccessible.
        $component->call('handleServiceChange', $etranger->getKey())
            ->assertForbidden();
    }

    private function makeDoctorFor(User $user, Service $service): void
    {
        Doctor::create([
            'user_id' => $user->getKey(),
            'service_id' => $service->getKey(),
        ]);
    }
}
