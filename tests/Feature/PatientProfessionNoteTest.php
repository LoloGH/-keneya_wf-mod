<?php

namespace Tests\Feature;

use App\Livewire\Reception\PatientRegistrationForm;
use App\Livewire\Service\PatientRecordPanel;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Visit;
use App\Services\SmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Profession et note au dossier patient (v3.2.5).
 *
 * Deux renseignements que l'accueil prend a l'oral et qui n'avaient nulle part
 * ou aller. Ils ne valent que s'ils se relisent : ces tests couvrent la saisie
 * *et* la lecture.
 */
class PatientProfessionNoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->mock(SmsGateway::class)->shouldReceive('send')->andReturnTrue();
    }

    public function test_la_profession_et_la_note_sont_enregistrees(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);

        Livewire::actingAs($this->makeReceptionist())
            ->test(PatientRegistrationForm::class)
            ->set('name', 'Aminata Traore')
            ->set('age', 34)
            ->set('gender', 'Femme')
            ->set('mobile', '76000000')
            ->set('profession', 'Cultivatrice')
            ->set('service_id', $service->getKey())
            ->set('note', 'Malentendante, accompagnee par sa fille.')
            ->call('save')
            ->assertHasNoErrors();

        $patient = Patient::firstOrFail();

        $this->assertSame('Cultivatrice', $patient->profession);
        $this->assertSame('Malentendante, accompagnee par sa fille.', $patient->note);
    }

    public function test_les_deux_champs_restent_facultatifs(): void
    {
        $service = Service::factory()->create();

        // L'accueil ne doit pas etre bloque parce qu'un patient ne veut pas
        // dire ce qu'il fait.
        Livewire::actingAs($this->makeReceptionist())
            ->test(PatientRegistrationForm::class)
            ->set('name', 'Moussa Diallo')
            ->set('age', 40)
            ->set('mobile', '76000001')
            ->set('service_id', $service->getKey())
            ->call('save')
            ->assertHasNoErrors();

        $patient = Patient::firstOrFail();

        $this->assertNull($patient->profession);
        $this->assertNull($patient->note);
    }

    public function test_le_formulaire_propose_les_deux_champs(): void
    {
        Service::factory()->create();

        Livewire::actingAs($this->makeReceptionist())
            ->test(PatientRegistrationForm::class)
            ->assertSee('Profession')
            ->assertSee('Note pour le service')
            ->assertSee('Identite du patient')
            ->assertSee('Passage du jour');
    }

    public function test_les_champs_se_vident_apres_un_enregistrement(): void
    {
        $service = Service::factory()->create();

        // Sinon la profession du patient precedent serait attribuee au suivant.
        Livewire::actingAs($this->makeReceptionist())
            ->test(PatientRegistrationForm::class)
            ->set('name', 'Aminata Traore')
            ->set('age', 34)
            ->set('mobile', '76000000')
            ->set('profession', 'Cultivatrice')
            ->set('service_id', $service->getKey())
            ->set('note', 'Une note.')
            ->call('save')
            ->assertSet('profession', '')
            ->assertSet('note', '');
    }

    public function test_le_medecin_lit_la_profession_et_la_note_au_dossier(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $doctor = $this->makeDoctor($service);

        $patient = Patient::factory()->create([
            'name' => 'Aminata Traore',
            'profession' => 'Cultivatrice',
            'note' => 'Malentendante, accompagnee par sa fille.',
        ]);
        $this->makeVisit($service, ['status' => Visit::STATUS_CALLED], $patient);

        Livewire::actingAs($doctor->user)
            ->test(PatientRecordPanel::class, ['serviceId' => $service->getKey()])
            ->call('open', $patient->getKey())
            ->assertSee('Cultivatrice')
            ->assertSee('Note de l\'accueil', escape: false)
            ->assertSee('Malentendante, accompagnee par sa fille.');
    }

    public function test_un_dossier_sans_note_n_affiche_pas_de_bloc_vide(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);

        $patient = Patient::factory()->create(['note' => null]);
        $this->makeVisit($service, ['status' => Visit::STATUS_CALLED], $patient);

        Livewire::actingAs($doctor->user)
            ->test(PatientRecordPanel::class, ['serviceId' => $service->getKey()])
            ->call('open', $patient->getKey())
            ->assertDontSee('Note de l\'accueil', escape: false);
    }
}
