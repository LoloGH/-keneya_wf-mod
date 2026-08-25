<?php

namespace Tests\Feature;

use App\Actions\RegisterPatient;
use App\Livewire\Reception\TodayVisits;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Visitor;
use App\Services\SmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Point 9 du v3.2 : ticket imprimable, patient et visiteur.
 *
 * Le contenu n'est pas le meme des deux cotes — on verifie donc les champs
 * attendus de chacun, et l'absence de ceux qui n'ont rien a y faire.
 */
class PrintTicketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(SmsGateway::class)->shouldReceive('send')->andReturnTrue();
    }

    public function test_le_ticket_patient_porte_les_champs_attendus(): void
    {
        [$ticket] = $this->makeCaisses();
        $consultation = Service::factory()->create(['name' => 'Medecine generale']);

        $visit = app(RegisterPatient::class)->execute([
            'name' => 'Moussa Keita',
            'age' => 41,
            'gender' => 'Homme',
            'mobile' => '70334455',
            'service_id' => $consultation->getKey(),
        ]);

        $patient = $visit->patient;

        $this->actingAs($this->makeReceptionist())
            ->get(route('reception.ticket.patient', $visit))
            ->assertOk()
            ->assertSee(hospital_name())
            ->assertSee($patient->patient_code)
            ->assertSee('Moussa Keita')
            ->assertSee((string) $visit->token)
            ->assertSee($ticket->name)
            // La destination apres paiement figure sur le ticket.
            ->assertSee('Medecine generale')
            // Le code personnel evite d'avoir a le dicter au guichet.
            ->assertSee($patient->access_code)
            ->assertSee('Ticket patient')
            ->assertSee('window.print()', escape: false);
    }

    public function test_le_ticket_visiteur_porte_le_patient_visite(): void
    {
        $service = Service::factory()->create(['name' => 'Chirurgie']);
        $patient = Patient::factory()->create(['name' => 'Moussa Keita']);

        $visiteur = Visitor::factory()->create([
            'name' => 'Awa Keita',
            'service_id' => $service->getKey(),
            'patient_id' => $patient->getKey(),
            'token' => 12,
        ]);

        $this->actingAs($this->makeReceptionist())
            ->get(route('reception.ticket.visitor', $visiteur))
            ->assertOk()
            ->assertSee(hospital_name())
            ->assertSee('Ticket visiteur')
            ->assertSee('12')
            ->assertSee('Chirurgie')
            ->assertSee('Awa Keita')
            ->assertSee('Moussa Keita')
            ->assertSee($visiteur->visitor_code)
            // Un visiteur n'a ni dossier ni code personnel.
            ->assertDontSee('Code personnel')
            ->assertDontSee($patient->patient_code);
    }

    public function test_le_ticket_masque_la_navigation_a_l_impression(): void
    {
        $service = Service::factory()->create();
        $visit = $this->makeVisit($service);

        $rendu = $this->actingAs($this->makeReceptionist())
            ->get(route('reception.ticket.patient', $visit))
            ->getContent();

        // Seul le ticket sort de l'imprimante.
        $this->assertStringContainsString('@media print', $rendu);
        $this->assertStringContainsString('.app-header', $rendu);
        $this->assertStringContainsString('.tabnav', $rendu);
        // Page autonome : aucune navigation n'est rendue en premier lieu.
        $this->assertStringNotContainsString('workspace__panel', $rendu);
    }

    public function test_la_liste_du_jour_permet_de_reimprimer_un_ticket(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine generale']);
        $visit = $this->makeVisit($service);

        $visiteur = Visitor::factory()->create([
            'service_id' => $service->getKey(),
            'token' => 4,
        ]);

        Livewire::actingAs($this->makeReceptionist())
            ->test(TodayVisits::class)
            ->assertSee(route('reception.ticket.patient', $visit), escape: false)
            ->assertSee(route('reception.ticket.visitor', $visiteur), escape: false);
    }

    public function test_le_ticket_n_est_accessible_a_aucun_autre_role(): void
    {
        $service = Service::factory()->create();
        $visit = $this->makeVisit($service);

        $this->actingAs($this->makeDoctor($service)->user)
            ->get(route('reception.ticket.patient', $visit))
            ->assertRedirect(route('service.home'));

        $this->actingAs($this->makeAdmin())
            ->get(route('reception.ticket.patient', $visit))
            ->assertRedirect(route('admin.home'));
    }
}
