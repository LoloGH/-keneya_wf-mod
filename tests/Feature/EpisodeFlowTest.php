<?php

namespace Tests\Feature;

use App\Livewire\Reception\PatientLookup;
use App\Livewire\Service\MyPatients;
use App\Livewire\Service\PatientRecordPanel;
use App\Models\Patient;
use App\Models\PatientHistory;
use App\Models\Service;
use App\Models\Visit;
use App\Services\PatientHistoryRecorder;
use App\Services\SmsGateway;
use App\Services\SmsSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Addendum v3 : identite permanente et passages distincts.
 *
 * Un patient qui revient six mois plus tard doit ouvrir un nouvel episode sous
 * le meme `patient_code`, sans jamais ecraser ni melanger le precedent.
 */
class EpisodeFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(SmsGateway::class)->shouldReceive('deliver')->andReturn(SmsSendResult::sent());
    }

    public function test_la_recherche_retrouve_un_patient_par_code_nom_ou_telephone(): void
    {
        $patient = Patient::factory()->create([
            'name' => 'Sekou Diarra',
            'mobile' => '76445566',
        ]);

        foreach ([$patient->patient_code, 'Sekou', '76445566'] as $term) {
            Livewire::actingAs($this->makeReceptionist())
                ->test(PatientLookup::class)
                ->set('search', $term)
                ->assertSee('Sekou Diarra');
        }
    }

    public function test_ouvrir_un_nouvel_episode_ne_cree_ni_patient_ni_code(): void
    {
        $ancien = Service::factory()->create();
        $nouveau = Service::factory()->create();

        $patient = Patient::factory()->create(['name' => 'Sekou Diarra']);
        $premier = $this->makeVisit($ancien, ['status' => Visit::STATUS_CLOSED], $patient);

        Livewire::actingAs($this->makeReceptionist())
            ->test(PatientLookup::class)
            ->set('search', $patient->patient_code)
            ->call('select', $patient->getKey())
            ->set('serviceId', $nouveau->getKey())
            ->set('reason', 'Retour pour douleurs abdominales, sans lien avec l\'episode precedent.')
            ->call('openEpisode')
            ->assertHasNoErrors();

        $this->assertSame(1, Patient::count());
        $this->assertSame(2, $patient->visits()->count());

        $second = $patient->visits()->orderByDesc('id')->first();

        $this->assertNotSame($premier->getKey(), $second->getKey());
        $this->assertSame($nouveau->getKey(), $second->service_id);
        $this->assertSame(Visit::STATUS_WAITING, $second->status);

        // Le motif du nouvel episode est trace, rattache a la bonne visite.
        $this->assertDatabaseHas('patient_history', [
            'visit_id' => $second->getKey(),
            'type' => PatientHistory::TYPE_REGISTRATION,
        ]);

        $entry = PatientHistory::where('visit_id', $second->getKey())->firstOrFail();
        $this->assertStringContainsString('douleurs abdominales', $entry->description);
    }

    public function test_la_confirmation_affiche_l_identite_avant_ouverture(): void
    {
        $patient = Patient::factory()->create(['name' => 'Aminata Sow', 'age' => 29]);

        Livewire::actingAs($this->makeReceptionist())
            ->test(PatientLookup::class)
            ->set('search', 'Aminata')
            ->call('select', $patient->getKey())
            ->assertSee('Confirmer', escape: false)
            ->assertSee($patient->patient_code)
            ->assertSee('29');
    }

    public function test_le_dossier_regroupe_les_episodes_chronologiquement(): void
    {
        $premierService = Service::factory()->create(['name' => 'Medecine Generale']);
        $secondService = Service::factory()->create(['name' => 'Maternite']);

        $patient = Patient::factory()->create();

        $ancien = $this->makeVisit($premierService, ['status' => Visit::STATUS_CLOSED], $patient);
        $ancien->forceFill(['opened_at' => now()->subMonths(6)])->save();

        $recent = $this->makeVisit($secondService, [], $patient);

        app(PatientHistoryRecorder::class)->record(
            $ancien, PatientHistory::TYPE_REGISTRATION, 'Premier passage.',
        );
        app(PatientHistoryRecorder::class)->record(
            $recent, PatientHistory::TYPE_REGISTRATION, 'Second passage.',
        );

        $component = Livewire::actingAs($this->makeDoctor($secondService)->user)
            ->test(PatientRecordPanel::class)
            ->call('open', $patient->getKey());

        // Les deux episodes sont visibles, distincts, sous le meme dossier.
        $component->assertSee('Medecine Generale')
            ->assertSee('Maternite')
            ->assertSee('Premier passage.')
            ->assertSee('Second passage.')
            ->assertSee($patient->patient_code);

        $episodes = $component->viewData('episodes');

        $this->assertCount(2, $episodes);
        // Le plus recent d'abord.
        $this->assertSame($recent->getKey(), $episodes[0]['visit']->getKey());
        $this->assertSame($ancien->getKey(), $episodes[1]['visit']->getKey());
    }

    public function test_mes_patients_liste_les_dossiers_clotures(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);

        $patient = Patient::factory()->create(['name' => 'Sekou Diarra']);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CLOSED], $patient);

        app(PatientHistoryRecorder::class)->record(
            $visit, PatientHistory::TYPE_CONSULTATION, 'Consultation.', null, $doctor,
        );

        // La cloture ne filtre jamais l'acces en lecture.
        Livewire::actingAs($doctor->user)
            ->test(MyPatients::class)
            ->assertSee('Sekou Diarra')
            ->set('includeClosed', false)
            ->assertDontSee('Sekou Diarra');
    }

    public function test_mes_patients_ne_montre_que_les_patients_du_medecin_connecte(): void
    {
        $service = Service::factory()->create();
        $mien = $this->makeDoctor($service);
        $autre = $this->makeDoctor($service);

        $aMoi = Patient::factory()->create(['name' => 'Patient A Moi']);
        $aLautre = Patient::factory()->create(['name' => 'Patient De Lautre']);

        $recorder = app(PatientHistoryRecorder::class);
        $recorder->record($this->makeVisit($service, [], $aMoi), PatientHistory::TYPE_CONSULTATION, 'Vue.', null, $mien);
        $recorder->record($this->makeVisit($service, [], $aLautre), PatientHistory::TYPE_CONSULTATION, 'Vue.', null, $autre);

        Livewire::actingAs($mien->user)
            ->test(MyPatients::class)
            ->assertSee('Patient A Moi')
            ->assertDontSee('Patient De Lautre');
    }
}
