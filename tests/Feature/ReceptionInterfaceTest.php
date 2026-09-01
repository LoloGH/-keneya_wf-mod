<?php

namespace Tests\Feature;

use App\Livewire\Board\WaitingBoard;
use App\Livewire\Reception\PatientRegistrationForm;
use App\Livewire\Reception\TodayVisits;
use App\Livewire\Reception\VisitorRegistrationForm;
use App\Livewire\Service\ServiceQueue;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Visit;
use App\Models\Visitor;
use App\Services\SmsGateway;
use App\Services\SmsSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReceptionInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(SmsGateway::class)->shouldReceive('deliver')->andReturn(SmsSendResult::sent());
    }

    public function test_le_formulaire_patient_refuse_une_saisie_incomplete(): void
    {
        Livewire::actingAs($this->makeReceptionist())
            ->test(PatientRegistrationForm::class)
            ->call('save')
            ->assertHasErrors(['name', 'age', 'mobile', 'service_id']);

        $this->assertSame(0, Patient::count());
        $this->assertSame(0, Visit::count());
    }

    public function test_les_tickets_se_suivent_dans_la_file_de_chaque_service(): void
    {
        $urgences = Service::factory()->create();
        $maternite = Service::factory()->create();

        $form = Livewire::actingAs($this->makeReceptionist())->test(PatientRegistrationForm::class);

        foreach ([[$urgences, 'Patient A'], [$urgences, 'Patient B'], [$maternite, 'Patient C']] as [$service, $name]) {
            $form->set('name', $name)
                ->set('age', 30)
                ->set('gender', 'Femme')
                ->set('mobile', '76000000')
                ->set('service_id', $service->getKey())
                ->call('save')
                ->assertHasNoErrors();
        }

        $this->assertSame([1, 2], Visit::where('service_id', $urgences->getKey())->orderBy('token')->pluck('token')->all());
        $this->assertSame([1], Visit::where('service_id', $maternite->getKey())->pluck('token')->all());
    }

    public function test_le_formulaire_visiteur_cree_une_fiche_avec_ticket(): void
    {
        $service = Service::factory()->create();
        $patient = Patient::factory()->create(['name' => 'Sekou Diarra']);

        Livewire::actingAs($this->makeReceptionist())
            ->test(VisitorRegistrationForm::class)
            ->set('name', 'Mariam Kone')
            ->set('service_id', $service->getKey())
            ->call('selectPatient', $patient->getKey())
            ->set('reason', 'Visite a un proche')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('HFD-V-00001');

        $this->assertSame(1, Visitor::count());

        // Enregistrer un visiteur ne cree aucun patient supplementaire : seul
        // le patient visite, cree pour ce test, existe.
        $this->assertSame(1, Patient::count());
        $this->assertSame($patient->getKey(), Visitor::first()->patient_id);

        // Le visiteur tire dans la meme sequence que les patients du service.
        $this->assertSame(1, Visitor::first()->token);
    }

    public function test_patients_et_visiteurs_ne_recoivent_jamais_le_meme_numero(): void
    {
        $service = Service::factory()->create();
        $receptionist = $this->makeReceptionist();

        Livewire::actingAs($receptionist)
            ->test(PatientRegistrationForm::class)
            ->set('name', 'Patient A')->set('age', 30)->set('gender', 'Homme')
            ->set('mobile', '76000000')->set('service_id', $service->getKey())
            ->call('save')->assertHasNoErrors();

        Livewire::actingAs($receptionist)
            ->test(VisitorRegistrationForm::class)
            ->set('name', 'Visiteur B')->set('service_id', $service->getKey())
            ->call('selectPatient', Patient::factory()->create()->getKey())
            ->call('save')->assertHasNoErrors();

        Livewire::actingAs($receptionist)
            ->test(PatientRegistrationForm::class)
            ->set('name', 'Patient C')->set('age', 40)->set('gender', 'Femme')
            ->set('mobile', '76000001')->set('service_id', $service->getKey())
            ->call('save')->assertHasNoErrors();

        $tokens = Visit::pluck('token')->concat(Visitor::pluck('token'))->sort()->values();

        $this->assertSame([1, 2, 3], $tokens->all());
        $this->assertCount(3, $tokens->unique());
    }

    public function test_la_liste_du_jour_filtre_sur_la_recherche(): void
    {
        $service = Service::factory()->create();
        $this->makeVisit($service, [], Patient::factory()->create(['name' => 'Aissata Toure']));
        $this->makeVisit($service, [], Patient::factory()->create(['name' => 'Boubacar Sangare']));

        Livewire::actingAs($this->makeReceptionist())
            ->test(TodayVisits::class)
            ->assertSee('Aissata Toure')
            ->assertSee('Boubacar Sangare')
            ->set('search', 'Aissata')
            ->assertSee('Aissata Toure')
            ->assertDontSee('Boubacar Sangare');
    }

    /**
     * Les files repartent a 1 chaque matin. Un patient de la veille ne doit
     * donc plus apparaitre dans la file du jour — sinon son numero entrerait
     * en collision avec un ticket reattribue aujourd'hui.
     */
    public function test_la_file_du_jour_ignore_les_patients_de_la_veille(): void
    {
        $service = Service::factory()->create();

        $veille = $this->makeVisit(
            $service,
            ['token' => 9],
            Patient::factory()->create(['name' => 'Patient de la veille']),
        );
        $veille->forceFill(['updated_at' => now()->subDay()])->saveQuietly();

        Livewire::actingAs($this->makeReceptionist())
            ->test(PatientRegistrationForm::class)
            ->set('name', 'Patient du jour')
            ->set('age', 22)
            ->set('gender', 'Homme')
            ->set('mobile', '76000000')
            ->set('service_id', $service->getKey())
            ->call('save')
            ->assertHasNoErrors();

        // Le ticket repart a 1 et la file du jour ne contient que le nouveau venu.
        $dujour = Visit::whereRelation('patient', 'name', 'Patient du jour')->firstOrFail();
        $this->assertSame(1, $dujour->token);

        $this->assertSame(
            [$dujour->getKey()],
            Visit::query()->inTodaysQueue($service->getKey())->pluck('id')->all(),
        );

        // La file affichee au medecin suit la meme regle.
        Livewire::actingAs($this->makeDoctor($service)->user)
            ->test(ServiceQueue::class, ['serviceId' => $service->getKey()])
            ->assertSee('Patient du jour')
            ->assertDontSee('Patient de la veille');
    }

    public function test_l_ecran_de_salle_d_attente_montre_le_ticket_en_cours(): void
    {
        $service = Service::factory()->create(['name' => 'Urgences']);
        $this->makeVisit($service, ['token' => 12, 'status' => Visit::STATUS_CALLED]);
        $this->makeVisit($service, ['token' => 13]);

        // Accessible sans authentification : c'est un affichage public.
        Livewire::test(WaitingBoard::class)
            ->assertSee('Urgences')
            ->assertSee('12')
            ->assertSee('13');
    }
}
