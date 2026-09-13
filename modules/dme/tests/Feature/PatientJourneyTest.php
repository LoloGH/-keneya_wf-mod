<?php

declare(strict_types=1);

namespace Keneya\Dme\Tests\Feature;

use Keneya\Dme\Models\Allergy;
use Keneya\Dme\Models\Appointment;
use Keneya\Dme\Models\Consultation;
use Keneya\Dme\Models\Hospitalization;
use Keneya\Dme\Models\ImagingOrder;
use Keneya\Dme\Models\LabOrder;
use Keneya\Dme\Models\MedicalDocument;
use Keneya\Dme\Models\NursingNote;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\Prescription;
use Keneya\Dme\Models\Service;
use Keneya\Dme\Models\SmsMessage;
use Keneya\Dme\Models\User;
use Keneya\Dme\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Keneya\Dme\Tests\TestCase;

/**
 * Parcours patient complet : critère d'acceptation global (§67).
 *
 * Ce test exécute, dans l'ordre et avec les rôles réellement habilités,
 * l'enchaînement exigé par la spécification :
 *
 *   Patient -> DME -> Consultation -> Constantes -> Examen clinique ->
 *   Diagnostic -> Ordonnance -> Laboratoire -> Résultat -> Imagerie ->
 *   Hospitalisation -> Soins -> Rendez-vous -> Documents -> Historique ->
 *   Audit -> Notification -> SMS
 *
 * Il vaut preuve que les écrans sont réellement interconnectés, et non
 * juxtaposés.
 */
class PatientJourneyTest extends TestCase
{
    use RefreshDatabase;

    private User $reception;

    private User $doctor;

    private User $nurse;

    private User $labTechnician;

    private User $radiologist;

    private User $pharmacist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();

        $this->reception = $this->userWithRole(Rbac::ROLE_RECEPTION);
        $this->doctor = $this->userWithRole(Rbac::ROLE_DOCTOR);
        $this->nurse = $this->userWithRole(Rbac::ROLE_NURSE);
        $this->labTechnician = $this->userWithRole(Rbac::ROLE_LAB);
        $this->radiologist = $this->userWithRole(Rbac::ROLE_RADIOLOGY);
        $this->pharmacist = $this->userWithRole(Rbac::ROLE_PHARMACIST);
    }

    public function test_le_parcours_patient_complet_fonctionne_de_bout_en_bout(): void
    {
        Storage::fake('local');

        $service = Service::where('code', 'MI')->firstOrFail();

        // ---------------------------------------------------------
        // 1. Création du patient par la réception (§12)
        // ---------------------------------------------------------
        $this->actingAs($this->reception)
            ->post(route('dme.patients.store'), [
                'last_name' => 'Traoré',
                'first_name' => 'Mamadou',
                'sex' => 'male',
                'birth_date' => now()->subYears(42)->toDateString(),
                'phone' => '+22370001001',
                'blood_group' => 'O+',
                'attending_doctor_id' => $this->doctor->id,
                'known_allergies' => 'Pénicilline',
                'chronic_conditions' => 'Hypertension artérielle',
                'emergency_contact' => [
                    'name' => 'Aminata Traoré',
                    'relationship' => 'Épouse',
                    'phone' => '+22370001002',
                ],
                'action' => 'open',
            ])
            ->assertRedirect();

        $patient = Patient::sole();
        $this->assertMatchesRegularExpression('/^PAT-\d{4}-000001$/', $patient->patient_number);
        $this->assertSame(1, $patient->allergies()->count());
        $this->assertSame(1, $patient->chronicConditions()->count());
        $this->assertSame(1, $patient->emergencyContacts()->count());

        // L'allergie créée à l'admission est ensuite qualifiée de sévère,
        // ce qui la fait remonter en alerte permanente du dossier (§13).
        $patient->allergies()->update(['severity' => 'severe', 'reaction' => 'Œdème de Quincke']);

        // ---------------------------------------------------------
        // 2. Ouverture du DME (§13-14)
        // ---------------------------------------------------------
        $this->actingAs($this->doctor)
            ->get(route('dme.patients.show', $patient))
            ->assertOk()
            ->assertSee($patient->patient_number)
            ->assertSee('Pénicilline')
            ->assertSee('Hypertension artérielle');

        // ---------------------------------------------------------
        // 3-6. Consultation avec constantes, examen clinique, diagnostic (§19-21)
        // ---------------------------------------------------------
        $this->actingAs($this->doctor)
            ->post(route('dme.consultations.store', $patient), [
                'started_at' => now()->format('Y-m-d\TH:i'),
                'type' => 'follow_up',
                'service_id' => $service->id,
                'reason' => 'Céphalées et fatigue à l\'effort',
                'history_of_illness' => 'Céphalées occipitales matinales depuis trois semaines.',
                'vitals' => [
                    'temperature' => 37.1,
                    'systolic' => 158,
                    'diastolic' => 95,
                    'heart_rate' => 84,
                    'weight' => 74.5,
                    'height' => 176,
                    'glycemia' => 1.42,
                ],
                'exam' => [
                    'general' => 'État général conservé.',
                    'cardiovascular' => 'Bruits du cœur réguliers, TA élevée aux deux bras.',
                ],
                'diagnoses' => [
                    ['label' => 'Hypertension artérielle essentielle', 'code' => 'I10',
                     'type' => 'primary', 'status' => 'confirmed'],
                ],
                'treatment_plan' => 'Introduction d\'un antihypertenseur.',
                'action' => 'save',
            ])
            ->assertRedirect();

        $consultation = Consultation::sole();
        $this->assertMatchesRegularExpression('/^CONS-\d{4}-000001$/', $consultation->consultation_number);

        // Constantes historisées et IMC dérivé (§20, §40)
        $vital = $consultation->vitalSigns()->sole();
        $this->assertSame(24.05, $vital->bmi);
        $this->assertTrue($vital->isOutOfRange('systolic'));

        // Examen clinique et diagnostic rattachés
        $this->assertSame(2, $consultation->clinicalNotes()->count());
        $this->assertSame('I10', $consultation->diagnoses()->sole()->code);

        // ---------------------------------------------------------
        // 7. Ordonnance et contrôle d'allergie (§22)
        // ---------------------------------------------------------
        $this->actingAs($this->doctor)
            ->post(route('dme.prescriptions.store', $patient), [
                'consultation_id' => $consultation->id,
                'issued_on' => now()->toDateString(),
                'items' => [
                    ['medication_name' => 'Amlodipine', 'dosage' => '5 mg', 'form' => 'Comprimé',
                     'route' => 'Orale', 'frequency' => '1 fois par jour', 'duration' => '90 jours'],
                    // Molécule de la famille des pénicillines : doit déclencher l'alerte.
                    ['medication_name' => 'Amoxicilline', 'dosage' => '500 mg', 'form' => 'Comprimé',
                     'route' => 'Orale', 'frequency' => '3 fois par jour', 'duration' => '7 jours'],
                ],
            ])
            ->assertRedirect();

        $prescription = Prescription::sole();
        $this->assertTrue(
            $prescription->hasAllergyWarnings(),
            'Le contrôle d\'allergie n\'a pas détecté l\'Amoxicilline face à l\'allergie à la pénicilline.'
        );
        // L'ordonnance existe malgré l'alerte : rien n'est supprimé d'office.
        $this->assertSame(2, $prescription->items()->count());

        // Validation sans confirmation explicite : refusée.
        $this->actingAs($this->doctor)
            ->post(route('dme.prescriptions.validate', $prescription))
            ->assertSessionHasErrors('acknowledge_allergy');
        $this->assertSame('draft', $prescription->fresh()->status);

        // Validation avec prise de connaissance de l'alerte : acceptée et tracée.
        $this->actingAs($this->doctor)
            ->post(route('dme.prescriptions.validate', $prescription), [
                'acknowledge_allergy' => 1,
                'allergy_justification' => 'Allergie ancienne, réévaluée en consultation d\'allergologie.',
            ])
            ->assertRedirect();

        $prescription->refresh();
        $this->assertSame('validated', $prescription->status);
        $this->assertTrue($prescription->allergy_warning_acknowledged);

        // ---------------------------------------------------------
        // 8-9. Laboratoire : demande puis résultat (§23)
        // ---------------------------------------------------------
        $this->actingAs($this->doctor)
            ->post(route('dme.laboratory.store', $patient), [
                'consultation_id' => $consultation->id,
                'requested_at' => now()->format('Y-m-d\TH:i'),
                'priority' => 'routine',
                'indication' => 'Bilan de retentissement.',
                'exams' => ['Glycémie à jeun', 'Créatininémie'],
            ])
            ->assertRedirect();

        $labOrder = LabOrder::sole();
        $this->assertMatchesRegularExpression('/^LAB-\d{4}-000001$/', $labOrder->order_number);
        $this->assertSame(2, $labOrder->items()->count());

        $items = $labOrder->items;

        $this->actingAs($this->labTechnician)
            ->post(route('dme.laboratory.results.store', $labOrder), [
                'results' => [
                    ['lab_order_item_id' => $items[0]->id, 'parameter' => 'Glycémie',
                     'value' => '1.42', 'unit' => 'g/L', 'reference_range' => '0.70 - 1.10', 'flag' => 'high'],
                    ['lab_order_item_id' => $items[1]->id, 'parameter' => 'Créatinine',
                     'value' => '9.8', 'unit' => 'mg/L', 'reference_range' => '7.0 - 13.0', 'flag' => 'normal'],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(2, $labOrder->results()->count());
        $this->assertSame('available', $labOrder->fresh()->status);

        $this->actingAs($this->labTechnician)
            ->post(route('dme.laboratory.validate', $labOrder))
            ->assertRedirect();
        $this->assertSame('validated', $labOrder->fresh()->status);

        // ---------------------------------------------------------
        // 10. Imagerie et compte rendu (§24)
        // ---------------------------------------------------------
        $this->actingAs($this->doctor)
            ->post(route('dme.imaging.store', $patient), [
                'consultation_id' => $consultation->id,
                'modality' => 'ultrasound',
                'body_site' => 'Abdomen complet',
                'requested_at' => now()->format('Y-m-d\TH:i'),
                'priority' => 'routine',
                'indication' => 'Bilan de stéatose.',
            ])
            ->assertRedirect();

        $imaging = ImagingOrder::sole();
        $this->assertNotNull($imaging->accession_number, 'Aucun numéro d\'accession attribué.');

        $this->actingAs($this->radiologist)
            ->post(route('dme.imaging.report.store', $imaging), [
                'findings' => 'Foie hyperéchogène homogène. Vésicule alithiasique.',
                'conclusion' => 'Stéatose hépatique de grade 1.',
                'is_abnormal' => 1,
                'status' => 'final',
            ])
            ->assertRedirect();

        $this->assertSame('reported', $imaging->fresh()->status);
        $this->assertSame('Stéatose hépatique de grade 1.', $imaging->fresh()->report->conclusion);

        // ---------------------------------------------------------
        // 11-12. Hospitalisation, suivi et soins (§25-26)
        // ---------------------------------------------------------
        $this->actingAs($this->doctor)
            ->post(route('dme.hospitalizations.store', $patient), [
                'service_id' => $service->id,
                'admitted_at' => now()->format('Y-m-d\TH:i'),
                'admission_reason' => 'Poussée hypertensive avec céphalées intenses.',
                'admission_diagnosis' => 'Poussée hypertensive',
                'room' => 'C-204',
                'bed' => 'Lit 2',
            ])
            ->assertRedirect();

        $stay = Hospitalization::sole();
        // L'admission crée d'office le premier événement de la timeline.
        $this->assertSame(1, $stay->events()->count());

        $this->actingAs($this->nurse)
            ->post(route('dme.nursing.store', $patient), [
                'hospitalization_id' => $stay->id,
                'type' => 'medication_administration',
                'occurred_at' => now()->format('Y-m-d\TH:i'),
                'title' => 'Administration d\'amlodipine',
                'medication_name' => 'Amlodipine',
                'medication_dose' => '5 mg',
                'medication_route' => 'Orale',
                'severity' => 'info',
            ])
            ->assertRedirect();

        $this->assertSame(1, NursingNote::where('patient_id', $patient->id)->count());

        $this->actingAs($this->doctor)
            ->post(route('dme.hospitalizations.discharge', $stay), [
                'discharged_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
                'discharge_diagnosis' => 'Poussée hypertensive contrôlée',
                'discharge_recommendations' => 'Régime hyposodé, autocontrôle tensionnel.',
                'discharge_type' => 'home',
            ])
            ->assertRedirect();

        $stay->refresh();
        $this->assertSame('discharged', $stay->status);
        $this->assertSame(2, $stay->events()->count());

        // ---------------------------------------------------------
        // 13. Rendez-vous : déclenche notification et SMS (§27, §36, §53)
        // ---------------------------------------------------------
        $this->actingAs($this->reception)
            ->post(route('dme.appointments.store', $patient), [
                'doctor_id' => $this->doctor->id,
                'scheduled_for' => now()->addDays(8)->setTime(9, 30)->format('Y-m-d\TH:i'),
                'duration_minutes' => 30,
                'reason' => 'Consultation de suivi',
                'reminder_enabled' => 1,
            ])
            ->assertRedirect();

        $appointment = Appointment::sole();
        $this->assertMatchesRegularExpression('/^RDV-\d{4}-000001$/', $appointment->appointment_number);

        // ---------------------------------------------------------
        // 14. Documents (§28, §42)
        // ---------------------------------------------------------
        $this->actingAs($this->doctor)
            ->post(route('dme.documents.store', $patient), [
                'title' => 'Compte rendu d\'échographie',
                'type' => 'imaging_report',
                'file' => UploadedFile::fake()->create('echographie.pdf', 40, 'application/pdf'),
            ])
            ->assertRedirect();

        $document = MedicalDocument::sole();
        $this->assertMatchesRegularExpression('/^DOC-\d{4}-000001$/', $document->document_number);
        Storage::disk('local')->assertExists($document->storage_path);

        $this->actingAs($this->doctor)
            ->get(route('dme.documents.download', $document))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // ---------------------------------------------------------
        // 15. Historique : tous les actes remontent dans la timeline (§29)
        // ---------------------------------------------------------
        $timeline = app(\Keneya\Dme\Services\Patients\MedicalTimeline::class)->build($patient->fresh());
        $types = $timeline->pluck('type')->unique();

        foreach (['consultations', 'diagnoses', 'prescriptions', 'laboratory', 'imaging', 'hospitalizations', 'documents'] as $type) {
            $this->assertTrue($types->contains($type), "Le type « {$type} » est absent de l'historique médical.");
        }

        // ---------------------------------------------------------
        // 16. Audit : chaque acte a laissé une trace (§30)
        // ---------------------------------------------------------
        $this->assertDatabaseHas('activity_log', ['patient_id' => $patient->id, 'action' => 'created']);
        $this->assertDatabaseHas('activity_log', ['patient_id' => $patient->id, 'action' => 'viewed']);
        $this->assertDatabaseHas('activity_log', ['patient_id' => $patient->id, 'action' => 'downloaded']);

        // ---------------------------------------------------------
        // 17-18. Notifications et SMS (§33, §35)
        // ---------------------------------------------------------
        // Le pharmacien a été notifié de l'ordonnance à délivrer.
        $this->assertDatabaseHas('dme_notifications', [
            'notifiable_id' => $this->pharmacist->id,
            'category' => 'prescription',
        ]);

        // Le prescripteur a été notifié des résultats disponibles.
        $this->assertDatabaseHas('dme_notifications', [
            'notifiable_id' => $this->doctor->id,
            'category' => 'lab_result',
        ]);

        // Trois SMS ont été déclenchés : ordonnance, résultat, rendez-vous
        // (+ rappel programmé la veille).
        $messages = SmsMessage::where('patient_id', $patient->id)->get();
        $this->assertGreaterThanOrEqual(3, $messages->count(), 'Les SMS métier n\'ont pas été déclenchés.');
        $this->assertTrue(
            $messages->every(fn (SmsMessage $m) => str_starts_with($m->recipient, '+223')),
            'Les numéros ne sont pas normalisés au format E.164.'
        );

        // ---------------------------------------------------------
        // 19. Le pharmacien peut délivrer l'ordonnance validée (§31)
        // ---------------------------------------------------------
        $this->actingAs($this->pharmacist)
            ->post(route('dme.prescriptions.dispense', $prescription))
            ->assertRedirect();

        $this->assertSame('dispensed', $prescription->fresh()->status);
    }
}
