<?php

namespace Tests\Feature;

use App\Actions\AddHandoffNote;
use App\Actions\AdmitPatient;
use App\Livewire\Service\Hospitalizations;
use App\Livewire\Shared\HandoffNotes;
use App\Models\HandoffNote;
use App\Models\Hospitalization;
use App\Models\PatientHistory;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\StaffMember;
use App\Models\StaffType;
use App\Models\User;
use App\Models\Visit;
use App\Services\SmsGateway;
use App\Services\SmsSendResult;
use App\Support\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Notes de releve entre equipes (v3.2.3, point 4).
 *
 * Les premiers tests ne construisent rien : ils verifient que la rotation du
 * personnel est bien deja reglee par la visibilite de service, comme la
 * conception le pretend. Batir un mecanisme de transfert par-dessus une
 * restriction imaginaire aurait ete la pire des reponses.
 */
class HandoffNoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(SmsGateway::class)->shouldReceive('deliver')->andReturn(SmsSendResult::sent());
    }

    private function deGarde(User $user, Service $service): void
    {
        Schedule::create([
            'user_id' => $user->getKey(),
            'service_id' => $service->getKey(),
            'date' => today()->toDateString(),
            'start_time' => '00:00:00',
            'end_time' => '23:59:00',
        ]);
    }

    private function makeNurse(Service $service, string $name = 'Infirmier'): User
    {
        $type = StaffType::firstOrCreate(
            ['slug' => StaffType::makeSlug($name)],
            ['name' => $name, 'matched_role' => null, 'capabilities' => [StaffType::CAP_CARE_TASKS]],
        );

        $user = User::factory()->create();

        StaffMember::create([
            'user_id' => $user->getKey(),
            'staff_type_id' => $type->getKey(),
            'service_id' => $service->getKey(),
        ]);

        return $user;
    }

    /** @return array{0: Service, 1: Hospitalization} */
    private function makeSejour(): array
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $doctor = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        return [$service, app(AdmitPatient::class)->execute($visit, $doctor)];
    }

    // --------------------- La rotation est deja reglee, on le verifie

    public function test_un_patient_hospitalise_est_visible_par_tout_medecin_du_service(): void
    {
        [$service, $hospitalisation] = $this->makeSejour();

        // Un confrere qui n'a pas admis le patient : « Patients hospitalises »
        // n'est pas filtre par medecin admettant, et ne doit pas l'etre.
        $confrere = $this->makeDoctor($service);

        Livewire::actingAs($confrere->user)
            ->test(Hospitalizations::class, ['serviceId' => $service->getKey()])
            ->assertSee($hospitalisation->patient->name);
    }

    // --------------------------------------------- Ecriture d'une note

    public function test_le_personnel_de_garde_laisse_une_note_lue_par_l_equipe_suivante(): void
    {
        [$service, $hospitalisation] = $this->makeSejour();

        $nuit = $this->makeNurse($service);
        $this->deGarde($nuit, $service);

        Livewire::actingAs($nuit)
            ->test(HandoffNotes::class, ['hospitalizationId' => $hospitalisation->getKey()])
            ->set('content', 'A mal dormi, la famille passe ce matin.')
            ->call('save')
            ->assertHasNoErrors();

        $note = HandoffNote::firstOrFail();

        $this->assertSame($nuit->getKey(), $note->written_by_user_id);
        $this->assertSame('A mal dormi, la famille passe ce matin.', $note->content);

        // L'equipe de jour la lit sans rien avoir a demander a personne.
        $jour = $this->makeNurse($service, 'Aide soignant');
        $this->deGarde($jour, $service);

        Livewire::actingAs($jour)
            ->test(HandoffNotes::class, ['hospitalizationId' => $hospitalisation->getKey()])
            ->assertSee('A mal dormi')
            ->assertSee($nuit->name);
    }

    public function test_la_note_rejoint_la_frise_du_dossier(): void
    {
        [$service, $hospitalisation] = $this->makeSejour();
        $infirmier = $this->makeNurse($service);
        $this->deGarde($infirmier, $service);

        app(AddHandoffNote::class)->execute($hospitalisation, $infirmier, 'Perfusion posee a 22h.');

        $ligne = PatientHistory::where('type', PatientHistory::TYPE_HANDOFF_NOTE)->latest('id')->firstOrFail();

        // Une note de releve fait partie du parcours du patient, pas d'un
        // carnet parallele.
        $this->assertStringContainsString('Perfusion posee a 22h.', $ligne->description);
        $this->assertSame($hospitalisation->patient_id, $ligne->patient_id);

        $trace = Activity::where('event', Audit::EVENT_HANDOFF_NOTE)->latest('id')->firstOrFail();
        $this->assertStringContainsString($infirmier->name, $trace->description);
    }

    public function test_le_medecin_ecrit_aussi_depuis_son_interface(): void
    {
        [$service, $hospitalisation] = $this->makeSejour();
        $doctor = $this->makeDoctor($service);
        $this->deGarde($doctor->user, $service);

        Livewire::actingAs($doctor->user)
            ->test(Hospitalizations::class, ['serviceId' => $service->getKey()])
            ->call('showHandoff', $hospitalisation->getKey())
            ->assertSee('Ajouter une note de releve');

        Livewire::actingAs($doctor->user)
            ->test(HandoffNotes::class, ['hospitalizationId' => $hospitalisation->getKey()])
            ->set('content', 'Revoir la tension demain matin.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($doctor->user_id, HandoffNote::firstOrFail()->written_by_user_id);
    }

    // ------------------------------------------------------ Garde-fous

    public function test_une_note_vide_est_refusee(): void
    {
        [$service, $hospitalisation] = $this->makeSejour();
        $infirmier = $this->makeNurse($service);
        $this->deGarde($infirmier, $service);

        Livewire::actingAs($infirmier)
            ->test(HandoffNotes::class, ['hospitalizationId' => $hospitalisation->getKey()])
            ->set('content', '   ')
            ->call('save')
            ->assertHasErrors('content');

        $this->assertSame(0, HandoffNote::count());
    }

    public function test_hors_garde_la_note_se_lit_mais_ne_s_ecrit_pas(): void
    {
        [$service, $hospitalisation] = $this->makeSejour();

        $auteur = $this->makeNurse($service);
        $this->deGarde($auteur, $service);
        app(AddHandoffNote::class)->execute($hospitalisation, $auteur, 'Perfusion posee a 22h.');

        // Meme service, meme type, mais aucun creneau couvrant l'heure.
        $horsGarde = $this->makeNurse($service, 'Aide soignant');

        Livewire::actingAs($horsGarde)
            ->test(HandoffNotes::class, ['hospitalizationId' => $hospitalisation->getKey()])
            ->assertSee('Perfusion posee a 22h.')
            ->assertSee("Vous n'etes pas de garde", escape: false)
            ->assertDontSee('Ajouter une note de releve');

        $this->expectException(InvalidArgumentException::class);
        app(AddHandoffNote::class)->execute($hospitalisation, $horsGarde, 'Tentative.');
    }

    public function test_une_hospitalisation_cloturee_n_accepte_plus_de_note(): void
    {
        [$service, $hospitalisation] = $this->makeSejour();
        $infirmier = $this->makeNurse($service);
        $this->deGarde($infirmier, $service);

        $hospitalisation->update(['status' => Hospitalization::STATUS_DISCHARGED]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cloturee');

        app(AddHandoffNote::class)->execute($hospitalisation->refresh(), $infirmier, 'Trop tard.');
    }

    public function test_la_section_releves_suit_la_capacite_soins(): void
    {
        [$service] = $this->makeSejour();

        $avecSoins = $this->makeNurse($service);
        $this->deGarde($avecSoins, $service);

        $this->actingAs($avecSoins)
            ->get('/staff/infirmier')
            ->assertOk()
            ->assertSee('Releves');
    }
}
