<?php

namespace Tests\Feature;

use App\Livewire\Admin\DoctorManager;
use App\Livewire\Admin\ServiceManager;
use App\Livewire\Reception\PatientRegistrationForm;
use App\Livewire\Service\IncomingReferrals;
use App\Livewire\Service\OutgoingReferrals;
use App\Livewire\Service\PatientRecordPanel;
use App\Livewire\Service\ServiceQueue;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\PatientHistory;
use App\Models\Referral;
use App\Models\Service;
use App\Services\SmsGateway;
use App\Support\Roles;
use ArrayObject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Le scenario d'acceptation de bout en bout, dans l'ordre du cahier des
 * charges : de la creation du service par l'admin jusqu'a la consultation du
 * dossier complet par le medecin prescripteur, sans jamais quitter /service.
 */
class AcceptanceScenarioTest extends TestCase
{
    use RefreshDatabase;

    /** @var ArrayObject<int, array{to: string, text: string}> */
    private ArrayObject $sms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->sms = new ArrayObject;

        $this->mock(SmsGateway::class)
            ->shouldReceive('send')
            ->andReturnUsing(function (string $to, string $text): bool {
                $this->sms[] = ['to' => $to, 'text' => $text];

                return true;
            });
    }

    public function test_le_scenario_complet(): void
    {
        $admin = $this->makeAdmin();
        $medecineGenerale = Service::create(['name' => 'Medecine Generale', 'kind' => Service::KIND_CLINIQUE]);

        // 1. L'admin cree « Radiologie » (plateau technique) et y rattache un medecin.
        Livewire::actingAs($admin)
            ->test(ServiceManager::class)
            ->set('name', 'Radiologie')
            ->set('kind', Service::KIND_PLATEAU_TECHNIQUE)
            ->call('save')
            ->assertHasNoErrors();

        $radiologie = Service::where('name', 'Radiologie')->firstOrFail();
        $this->assertSame(Service::KIND_PLATEAU_TECHNIQUE, $radiologie->kind);

        Livewire::actingAs($admin)
            ->test(DoctorManager::class)
            ->set('name', 'Dr Amadou Cisse')
            ->set('email', 'radiologie@keneya.test')
            ->set('password', 'motdepasse')
            ->set('phone', '76000004')
            ->set('service_id', $radiologie->getKey())
            ->call('save')
            ->assertHasNoErrors();

        $radiologue = Doctor::where('service_id', $radiologie->getKey())->firstOrFail();
        $this->assertTrue($radiologue->user->hasRole(Roles::DOCTOR));

        // L'admin cree aussi « Echographie » et son praticien, plus le medecin
        // de Medecine Generale qui suivra le patient.
        Livewire::actingAs($admin)
            ->test(ServiceManager::class)
            ->set('name', 'Echographie')
            ->set('kind', Service::KIND_PLATEAU_TECHNIQUE)
            ->call('save');

        $echographie = Service::where('name', 'Echographie')->firstOrFail();
        $echographiste = $this->makeDoctor($echographie);
        $generaliste = $this->makeDoctor($medecineGenerale, '76000001');

        // 2. La receptionniste enregistre un patient sur Medecine Generale.
        $receptionist = $this->makeReceptionist();

        Livewire::actingAs($receptionist)
            ->test(PatientRegistrationForm::class)
            ->set('name', 'Sekou Diarra')
            ->set('age', 41)
            ->set('gender', 'Homme')
            ->set('mobile', '76445566')
            ->set('service_id', $medecineGenerale->getKey())
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('HFD-00001');

        $patient = Patient::where('patient_code', 'HFD-00001')->firstOrFail();
        $this->assertSame($medecineGenerale->getKey(), $patient->service_id);

        // 3. Le medecin de Medecine Generale appelle le patient puis l'envoie
        //    vers Echographie avec des instructions.
        $queue = Livewire::actingAs($generaliste->user)
            ->test(ServiceQueue::class, ['serviceId' => $medecineGenerale->getKey()]);

        $queue->call('callNext')->assertHasNoErrors();
        $this->assertSame(Patient::STATUS_CALLED, $patient->refresh()->status);

        $queue->call('startReferral', $patient->getKey())
            ->set('toServiceId', $echographie->getKey())
            ->set('instructions', 'Echographie abdominale a jeun.')
            ->call('sendReferral')
            ->assertHasNoErrors();

        $referral = Referral::firstOrFail();
        $patient->refresh();

        // 4. Le patient recoit un SMS l'orientant vers Echographie avec un
        //    nouveau ticket.
        $orientation = collect($this->sms->getArrayCopy())
            ->last(fn (array $message) => $message['to'] === '76445566'
                && str_contains($message['text'], 'Echographie'));

        $this->assertNotNull($orientation);
        $this->assertStringContainsString((string) $patient->token, $orientation['text']);
        $this->assertSame($echographie->getKey(), $patient->service_id);

        // 5. Le praticien d'Echographie, sur son propre poste, voit le renvoi
        //    et saisit un resultat.
        Livewire::actingAs($echographiste->user)
            ->test(IncomingReferrals::class, ['serviceId' => $echographie->getKey()])
            ->assertSee('Sekou Diarra')
            ->assertSee('Echographie abdominale a jeun.')
            ->call('startAnswer', $referral->getKey())
            ->set('resultText', 'Foie et reins sans particularite.')
            ->call('submitResult')
            ->assertHasNoErrors();

        // 6. Le medecin prescripteur voit le resultat dans son panneau.
        Livewire::actingAs($generaliste->user)
            ->test(OutgoingReferrals::class, ['serviceId' => $medecineGenerale->getKey()])
            ->assertSee('Foie et reins sans particularite.')
            ->assertSee('Sekou Diarra');

        // 7. Toujours depuis /service, il ouvre le dossier complet du patient.
        Livewire::actingAs($generaliste->user)
            ->test(PatientRecordPanel::class)
            ->call('open', $patient->getKey())
            ->assertSee('HFD-00001')
            ->assertSee('Enregistrement')
            ->assertSee('Consultation')
            ->assertSee('Renvoi envoye')
            ->assertSee('Resultat de renvoi');

        $this->assertSame(
            [
                PatientHistory::TYPE_REGISTRATION,
                PatientHistory::TYPE_CONSULTATION,
                PatientHistory::TYPE_REFERRAL_SENT,
                PatientHistory::TYPE_REFERRAL_RESULT,
            ],
            PatientHistory::where('patient_id', $patient->getKey())->orderBy('id')->pluck('type')->all(),
        );

        // Le dossier n'a jamais ete duplique.
        $this->assertSame(1, Patient::count());

        // 8. Cloisonnement : la receptionniste ne peut atteindre ni /admin ni /service.
        $this->actingAs($receptionist)->get('/admin')->assertRedirect(route('reception.home'));
        $this->actingAs($receptionist)->get('/service')->assertRedirect(route('reception.home'));
        $this->actingAs($receptionist)->get('/reception')->assertOk();
    }
}
