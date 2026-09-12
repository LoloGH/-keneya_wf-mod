<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Keneya\Dme\Models\CareOrder;
use Keneya\Dme\Models\Consultation;
use Keneya\Dme\Models\Hospitalization;
use Keneya\Dme\Models\ImagingOrder;
use Keneya\Dme\Models\LabOrder;
use Keneya\Dme\Models\MedicalDocument;
use Keneya\Dme\Models\Patient as DmePatient;
use Keneya\Dme\Models\Prescription;
use Tests\TestCase;

/**
 * Verification publique d'un document du dossier medical par QR code (v3.4).
 *
 * Chaque type verifiable a son propre test de base : le prefixe designe a la
 * fois un modele et une colonne, et LAB/IMG partagent la meme colonne
 * (`order_number`) sous deux tables differentes, ce qui est precisement le
 * risque de confusion que ces tests couvrent.
 */
class DocumentVerificationTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------- References valides

    public function test_une_reference_de_patient_est_verifiee(): void
    {
        $patient = DmePatient::factory()->create();

        $this->get(route('documents.verifier', $patient->patient_number))
            ->assertOk()
            ->assertSee('Document verifie')
            ->assertSee('Patient')
            ->assertSee($patient->patient_number);
    }

    public function test_une_reference_de_consultation_est_verifiee(): void
    {
        $patient = DmePatient::factory()->create();
        $consultation = Consultation::factory()->create([
            'patient_id' => $patient->getKey(),
            'status' => 'completed',
        ]);

        $this->get(route('documents.verifier', $consultation->consultation_number))
            ->assertOk()
            ->assertSee('Document verifie')
            ->assertSee('Consultation')
            ->assertSee($consultation->consultation_number)
            ->assertSee(Consultation::STATUSES['completed']);
    }

    public function test_une_reference_d_ordonnance_est_verifiee(): void
    {
        $patient = DmePatient::factory()->create();
        $prescription = Prescription::factory()->create([
            'patient_id' => $patient->getKey(),
            'status' => 'validated',
        ]);

        $this->get(route('documents.verifier', $prescription->prescription_number))
            ->assertOk()
            ->assertSee('Document verifie')
            ->assertSee('Ordonnance')
            ->assertSee($prescription->prescription_number)
            ->assertSee(Prescription::STATUSES['validated']);
    }

    public function test_une_reference_de_demande_de_laboratoire_est_verifiee(): void
    {
        $patient = DmePatient::factory()->create();
        $labOrder = LabOrder::factory()->create([
            'patient_id' => $patient->getKey(),
            'status' => 'validated',
        ]);

        $this->get(route('documents.verifier', $labOrder->order_number))
            ->assertOk()
            ->assertSee('Document verifie')
            ->assertSee('laboratoire')
            ->assertSee($labOrder->order_number)
            ->assertSee(LabOrder::STATUSES['validated']);
    }

    public function test_une_reference_de_demande_d_imagerie_est_verifiee(): void
    {
        $patient = DmePatient::factory()->create();
        $imagingOrder = ImagingOrder::create([
            'patient_id' => $patient->getKey(),
            'requested_at' => now(),
            'status' => 'reported',
        ]);

        $this->get(route('documents.verifier', $imagingOrder->order_number))
            ->assertOk()
            ->assertSee('Document verifie')
            ->assertSee('imagerie')
            ->assertSee($imagingOrder->order_number)
            ->assertSee(ImagingOrder::STATUSES['reported']);
    }

    /**
     * LAB et IMG partagent la colonne `order_number` : seul le prefixe de la
     * reference distingue les deux tables. Ce test cree les deux avec un
     * suffixe numerique volontairement proche et verifie qu'aucune des deux
     * routes ne resout vers l'autre modele.
     */
    public function test_les_prefixes_lab_et_img_ne_se_confondent_jamais(): void
    {
        $patient = DmePatient::factory()->create();

        $labOrder = LabOrder::factory()->create(['patient_id' => $patient->getKey()]);
        $imagingOrder = ImagingOrder::create([
            'patient_id' => $patient->getKey(),
            'requested_at' => now(),
        ]);

        $this->get(route('documents.verifier', $labOrder->order_number))
            ->assertOk()
            ->assertSee('laboratoire')
            ->assertDontSee('imagerie');

        $this->get(route('documents.verifier', $imagingOrder->order_number))
            ->assertOk()
            ->assertSee('imagerie')
            ->assertDontSee('laboratoire');
    }

    public function test_une_reference_d_hospitalisation_est_verifiee(): void
    {
        $patient = DmePatient::factory()->create();
        $hospitalization = Hospitalization::create([
            'patient_id' => $patient->getKey(),
            'admitted_at' => now(),
            'status' => 'discharged',
        ]);

        $this->get(route('documents.verifier', $hospitalization->hospitalization_number))
            ->assertOk()
            ->assertSee('Document verifie')
            ->assertSee('Hospitalisation')
            ->assertSee($hospitalization->hospitalization_number)
            ->assertSee(Hospitalization::STATUSES['discharged']);
    }

    public function test_une_reference_de_soin_est_verifiee(): void
    {
        $patient = DmePatient::factory()->create();
        $prescripteur = User::factory()->create();

        $careOrder = CareOrder::create([
            'patient_id' => $patient->getKey(),
            'prescriber_id' => $prescripteur->getKey(),
            'title' => 'Pansement',
            'starts_at' => now(),
            'status' => 'planned',
        ]);

        // Le trait HasBusinessIdentifier n'est pas surcharge sur CareOrder :
        // sa colonne d'identifiant est celle par defaut, `reference`.
        $this->assertSame('reference', $careOrder->identifierColumn());

        $this->get(route('documents.verifier', $careOrder->reference))
            ->assertOk()
            ->assertSee('Document verifie')
            ->assertSee('Soin')
            ->assertSee($careOrder->reference)
            ->assertSee(CareOrder::STATUSES['planned']);
    }

    public function test_une_reference_de_document_est_verifiee(): void
    {
        $patient = DmePatient::factory()->create();
        $document = MedicalDocument::create([
            'patient_id' => $patient->getKey(),
            'title' => 'Compte rendu',
            'storage_path' => 'medical-documents/rapport.pdf',
            'status' => 'final',
        ]);

        $this->get(route('documents.verifier', $document->document_number))
            ->assertOk()
            ->assertSee('Document verifie')
            ->assertSee($document->document_number);
    }

    // ----------------------------------------------- References invalides

    public function test_une_reference_inexistante_est_refusee_proprement(): void
    {
        $this->get(route('documents.verifier', 'ORD-2026-999999'))
            ->assertOk()
            ->assertSee('Document non verifie')
            ->assertDontSee('Document verifie');
    }

    public function test_un_prefixe_inconnu_est_refuse_proprement(): void
    {
        $this->get(route('documents.verifier', 'ZZZ-2026-000001'))
            ->assertOk()
            ->assertSee('Document non verifie');
    }

    public function test_une_reference_malformee_est_refusee_proprement(): void
    {
        $this->get(route('documents.verifier', 'ceci-n-est-pas-une-reference'))
            ->assertOk()
            ->assertSee('Document non verifie');

        $this->get(route('documents.verifier', 'PAT2026000001'))
            ->assertOk()
            ->assertSee('Document non verifie');
    }

    // ------------------------------------------------------- Acces public

    public function test_la_verification_est_accessible_sans_authentification(): void
    {
        $patient = DmePatient::factory()->create();

        $this->get(route('documents.verifier', $patient->patient_number))->assertOk();

        $this->assertGuest();
    }

    // --------------------------------------------------- Donnees exposees

    /**
     * La page publique ne doit jamais laisser deviner le contenu medical :
     * ni le nom du patient, ni le motif d'une consultation, ni le chemin ou
     * le disque de stockage d'un document.
     */
    public function test_les_donnees_medicales_et_techniques_ne_sont_jamais_exposees(): void
    {
        $patient = DmePatient::factory()->create([
            'last_name' => 'Traore',
            'first_name' => 'Fatoumata',
        ]);

        $consultation = Consultation::factory()->create([
            'patient_id' => $patient->getKey(),
            'reason' => 'Suspicion de tuberculose pulmonaire',
        ]);

        $document = MedicalDocument::create([
            'patient_id' => $patient->getKey(),
            'title' => 'Compte rendu confidentiel',
            'storage_path' => 'medical-documents/prive/rapport-secret.pdf',
            'disk' => 's3-confidentiel',
        ]);

        $reponsePatient = $this->get(route('documents.verifier', $patient->patient_number));
        $reponsePatient->assertOk()->assertDontSee('Traore')->assertDontSee('Fatoumata');

        $reponseConsultation = $this->get(route('documents.verifier', $consultation->consultation_number));
        $reponseConsultation->assertOk()
            ->assertDontSee('Traore')
            ->assertDontSee('tuberculose');

        $reponseDocument = $this->get(route('documents.verifier', $document->document_number));
        $reponseDocument->assertOk()
            ->assertDontSee('medical-documents/prive/rapport-secret.pdf')
            ->assertDontSee('s3-confidentiel')
            ->assertDontSee('storage_path')
            ->assertDontSee('disk');
    }

    // --------------------------------------------------------------- QR

    /**
     * PdfGenerator (keneya-dme_mod) construit l'URL du QR code par simple
     * concatenation : rtrim(config('app.url'), '/').'/documents/verifier/'.
     * $reference. Ce test ne rejoue pas le rendu PDF, il verifie que cette
     * URL, construite exactement de la meme facon, est bien celle que sert
     * la route publique.
     */
    public function test_l_url_construite_par_le_generateur_pdf_pointe_vers_la_route_publique(): void
    {
        $patient = DmePatient::factory()->create();
        $prescription = Prescription::factory()->create([
            'patient_id' => $patient->getKey(),
            'status' => 'validated',
        ]);

        $urlDuQrCode = rtrim((string) config('app.url'), '/').'/documents/verifier/'.$prescription->prescription_number;

        $this->assertSame($urlDuQrCode, route('documents.verifier', $prescription->prescription_number));

        $this->get($urlDuQrCode)
            ->assertOk()
            ->assertSee('Document verifie');
    }
}
