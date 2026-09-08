<?php

namespace Tests\Feature;

use App\Actions\CheckInAppointment;
use App\Actions\Dme\CreateMedicalPrescription;
use App\Livewire\Reception\TicketCashier;
use App\Livewire\Reception\TodayAppointments;
use App\Livewire\Service\ConsultationActions;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\PatientHistory;
use App\Models\Service;
use App\Models\Visit;
use App\Services\SmsGateway;
use App\Services\SmsSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Keneya\Dme\Models\Prescription;
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

        $this->mock(SmsGateway::class)->shouldReceive('deliver')->andReturn(SmsSendResult::sent());
    }

    // ----------------------------------------------------------------- Caisse

    /**
     * La caisse a quitte /reception et /service : elle a son role et son
     * interface. Ces tests vivent desormais dans CaisseFlowTest ; on verifie
     * seulement ici que les anciennes portes sont bien condamnees.
     */
    public function test_le_medecin_n_a_plus_aucune_fonction_de_caisse(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);

        $composant = Livewire::actingAs($doctor->user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()]);

        foreach (['recordPayment', 'amount'] as $vestige) {
            $this->assertFalse(
                method_exists($composant->instance(), $vestige)
                    || property_exists($composant->instance(), $vestige),
                "L'interface du medecin ne doit plus exposer « {$vestige} ».",
            );
        }

        $composant->assertDontSee('Caisse');
    }

    public function test_l_accueil_n_a_plus_de_section_caisse(): void
    {
        $this->assertFalse(
            class_exists(TicketCashier::class),
            'Le composant de caisse de l\'accueil doit avoir disparu.',
        );

        $this->actingAs($this->makeReceptionist())
            ->get('/reception')
            ->assertOk()
            ->assertDontSee('Caisse Ticket');
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
            ->set('prescriptionLines.0.medicament', 'Paracetamol 500 mg')
            ->set('prescriptionLines.0.posologie', '3 fois par jour')
            ->set('prescriptionLines.0.duree', '5 jours')
            ->call('savePrescription')
            ->assertHasNoErrors();

        $prescription = Prescription::firstOrFail();

        // L'ordonnance vit dans le dossier medical (v3.3.1) : elle designe le
        // compte du prescripteur, et se rattache au patient, non au passage.
        $this->assertSame($doctor->user_id, $prescription->doctor_id);
        $this->assertSame($visit->patient->dossierMedical()->getKey(), $prescription->patient_id);

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

        $prescription = app(CreateMedicalPrescription::class)
            ->execute($visit, $auteur, [['medicament' => 'Repos et hydratation']]);

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
