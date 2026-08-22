<?php

namespace Tests\Feature;

use App\Actions\CheckInAppointment;
use App\Actions\CreatePrescription;
use App\Livewire\Reception\TicketCashier;
use App\Livewire\Reception\TodayAppointments;
use App\Livewire\Service\ConsultationActions;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\PatientHistory;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Service;
use App\Models\Visit;
use App\Services\SmsGateway;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Point 9 de l'addendum v2 : caisse, ordonnances, rendez-vous.
 */
class CashPrescriptionAppointmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(SmsGateway::class)->shouldReceive('send')->andReturnTrue();
    }

    // ----------------------------------------------------------------- Caisse

    public function test_la_caisse_ticket_encaisse_a_l_accueil(): void
    {
        $service = Service::factory()->create();
        $visit = $this->makeVisit($service);
        $receptionist = $this->makeReceptionist();

        Livewire::actingAs($receptionist)
            ->test(TicketCashier::class)
            ->set('visitId', $visit->getKey())
            ->set('amount', 2500)
            ->call('record')
            ->assertHasNoErrors();

        $payment = Payment::firstOrFail();

        $this->assertSame(Payment::TYPE_TICKET, $payment->type);
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame(2500, $payment->amount);
        $this->assertSame($visit->getKey(), $payment->visit_id);
        $this->assertSame($receptionist->getKey(), $payment->recorded_by_user_id);
        $this->assertSame('2 500 FCFA', $payment->formattedAmount());
    }

    public function test_la_caisse_services_encaisse_un_acte_au_service(): void
    {
        $service = Service::factory()->plateauTechnique()->create();
        $doctor = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        Livewire::actingAs($doctor->user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('amount', 15000)
            ->call('recordPayment')
            ->assertHasNoErrors();

        $payment = Payment::firstOrFail();

        $this->assertSame(Payment::TYPE_SERVICE, $payment->type);
        $this->assertSame($service->getKey(), $payment->service_id);
        $this->assertSame(15000, $payment->amount);
    }

    public function test_un_montant_nul_ou_negatif_est_refuse(): void
    {
        $service = Service::factory()->create();
        $visit = $this->makeVisit($service);

        Livewire::actingAs($this->makeReceptionist())
            ->test(TicketCashier::class)
            ->set('visitId', $visit->getKey())
            ->set('amount', 0)
            ->call('record')
            ->assertHasErrors('amount');

        $this->assertSame(0, Payment::count());
    }

    public function test_un_praticien_ne_peut_pas_encaisser_pour_un_autre_service(): void
    {
        $mien = Service::factory()->create();
        $autre = Service::factory()->create();
        $doctor = $this->makeDoctor($mien);
        $visitAilleurs = $this->makeVisit($autre, ['status' => Visit::STATUS_CALLED]);

        // La visite d'un autre service est introuvable depuis cette interface :
        // le composant ne cherche que dans son propre service.
        $this->expectException(ModelNotFoundException::class);

        try {
            Livewire::actingAs($doctor->user)
                ->test(ConsultationActions::class, ['serviceId' => $mien->getKey()])
                ->set('visitId', $visitAilleurs->getKey())
                ->set('amount', 5000)
                ->call('recordPayment');
        } finally {
            $this->assertSame(0, Payment::count());
        }
    }

    // ------------------------------------------------------------ Ordonnance

    public function test_une_ordonnance_est_tracee_dans_l_historique_et_exportable_en_pdf(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        Livewire::actingAs($doctor->user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('tab', 'ordonnance')
            ->set('prescription', 'Paracetamol 500 mg, 3 fois par jour pendant 5 jours.')
            ->call('savePrescription')
            ->assertHasNoErrors();

        $prescription = Prescription::firstOrFail();

        $this->assertSame($doctor->getKey(), $prescription->doctor_id);
        $this->assertSame($visit->getKey(), $prescription->visit_id);

        $this->assertDatabaseHas('patient_history', [
            'visit_id' => $visit->getKey(),
            'type' => PatientHistory::TYPE_PRESCRIPTION,
        ]);

        $response = $this->actingAs($doctor->user)->get(route('service.prescription.pdf', $prescription));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_un_medecin_ne_telecharge_pas_l_ordonnance_d_un_confrere(): void
    {
        $service = Service::factory()->create();
        $auteur = $this->makeDoctor($service);
        $confrere = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        $prescription = app(CreatePrescription::class)
            ->execute($visit, $auteur, 'Repos et hydratation.');

        $this->actingAs($confrere->user)
            ->get(route('service.prescription.pdf', $prescription))
            ->assertForbidden();
    }

    // ----------------------------------------------------------- Rendez-vous

    public function test_le_medecin_fixe_un_rendez_vous_en_fin_de_consultation(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        Livewire::actingAs($doctor->user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('tab', 'rendez-vous')
            ->set('appointmentAt', now()->addWeek()->format('Y-m-d\TH:i'))
            ->call('saveAppointment')
            ->assertHasNoErrors();

        $appointment = Appointment::firstOrFail();

        $this->assertSame(Appointment::STATUS_SCHEDULED, $appointment->status);
        $this->assertSame($visit->patient_id, $appointment->patient_id);
    }

    public function test_un_rendez_vous_dans_le_passe_est_refuse(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        Livewire::actingAs($doctor->user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('tab', 'rendez-vous')
            ->set('appointmentAt', now()->subDay()->format('Y-m-d\TH:i'))
            ->call('saveAppointment')
            ->assertHasErrors('appointmentAt');
    }

    /**
     * « Orienter le patient » : le patient attendu se presente et entre dans la
     * file sans reenregistrement — un nouveau passage, pas une nouvelle identite.
     */
    public function test_orienter_le_patient_ouvre_une_visite_sans_nouveau_dossier(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);
        $patient = Patient::factory()->create(['name' => 'Sekou Diarra']);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->getKey(),
            'doctor_id' => $doctor->getKey(),
            'service_id' => $service->getKey(),
            'scheduled_at' => now()->addHour(),
        ]);

        Livewire::actingAs($this->makeReceptionist())
            ->test(TodayAppointments::class)
            ->call('checkIn', $appointment->getKey());

        $appointment->refresh();

        $this->assertSame(Appointment::STATUS_CHECKED_IN, $appointment->status);
        $this->assertNotNull($appointment->visit_id);

        // Aucune identite supplementaire n'a ete creee.
        $this->assertSame(1, Patient::count());
        $this->assertSame(1, Visit::count());

        $visit = Visit::firstOrFail();
        $this->assertSame($patient->getKey(), $visit->patient_id);
        $this->assertSame(Visit::STATUS_WAITING, $visit->status);
        $this->assertSame(1, $visit->token);
    }

    public function test_un_rendez_vous_manque_est_distinct_d_une_annulation(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);

        $manque = Appointment::factory()->create([
            'doctor_id' => $doctor->getKey(),
            'service_id' => $service->getKey(),
            'scheduled_at' => now()->addHour(),
        ]);
        $annule = Appointment::factory()->create([
            'doctor_id' => $doctor->getKey(),
            'service_id' => $service->getKey(),
            'scheduled_at' => now()->addHours(2),
        ]);

        $action = app(CheckInAppointment::class);
        $action->markNoShow($manque);
        $action->cancel($annule);

        $this->assertSame(Appointment::STATUS_NO_SHOW, $manque->refresh()->status);
        $this->assertSame(Appointment::STATUS_CANCELLED, $annule->refresh()->status);
    }

    public function test_un_patient_deja_arrive_ne_peut_plus_etre_annule(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);

        $appointment = Appointment::factory()->create([
            'doctor_id' => $doctor->getKey(),
            'service_id' => $service->getKey(),
            'scheduled_at' => now()->addHour(),
        ]);

        $action = app(CheckInAppointment::class);
        $action->execute($appointment);

        $this->expectException(\InvalidArgumentException::class);

        $action->cancel($appointment->refresh());
    }
}
