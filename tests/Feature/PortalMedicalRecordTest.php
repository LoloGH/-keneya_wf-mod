<?php

namespace Tests\Feature;

use App\Actions\Dme\OrderImaging;
use App\Actions\Dme\OrderLaboratory;
use App\Livewire\Portal\PatientPortal;
use App\Models\Patient;
use App\Models\Service;
use App\Support\Dme\PatientProjection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Keneya\Dme\Models\LabResult;
use Keneya\Dme\Models\MedicalDocument;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Le dossier medical, cote patient (v3.4).
 *
 * Le portail affichait les ordonnances et les pieces jointes de WorkFlow, et
 * rien d'autre. Depuis la v3.3.1 tout le contenu clinique vit dans le dossier
 * medical : un patient venu chercher son resultat d'analyse ou le compte rendu
 * de son echographie trouvait donc une page qui ne mentionnait meme pas
 * l'examen. Ces tests verifient les trois sections ajoutees, et surtout ce
 * qu'elles refusent d'afficher.
 *
 * La discipline tient en une phrase : le portail ne montre que ce que
 * l'etablissement a arrete. Une analyse encore au laboratoire, un compte rendu
 * a l'etat de brouillon, une valeur non validee par le biologiste ne sont pas
 * des informations mais des travaux en cours, et le patient les lit seul, sans
 * personne a qui demander ce qu'elles valent.
 */
class PortalMedicalRecordTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------- Resultats d'analyses

    public function test_une_analyse_rendue_s_affiche_avec_sa_conclusion(): void
    {
        [$patient, $demande] = $this->analyse();

        $demande->update([
            'conclusion' => 'Goutte epaisse negative.',
            'status' => 'available',
            'completed_at' => now(),
        ]);

        $this->ouvrir($patient)
            ->assertSee('Mes resultats d\'analyses (1)', escape: false)
            ->assertSee('Goutte epaisse negative.');
    }

    /**
     * Une demande encore au laboratoire n'a rien a dire au patient, et
     * l'annoncer « en attente » ne ferait qu'inquieter.
     */
    public function test_une_analyse_non_rendue_reste_invisible(): void
    {
        [$patient, $demande] = $this->analyse();

        $demande->update(['conclusion' => 'Brouillon du biologiste.']);

        $this->ouvrir($patient)
            ->assertSee('Aucun resultat d\'analyse', escape: false)
            ->assertDontSee('Brouillon du biologiste.');
    }

    public function test_une_valeur_validee_par_le_biologiste_s_affiche(): void
    {
        [$patient, $demande] = $this->analyse();

        $demande->update(['status' => 'validated', 'completed_at' => now()]);
        $this->mesurer($demande, 'Hemoglobine', '11.2', validee: true);

        $this->ouvrir($patient)
            ->assertSee('Hemoglobine')
            ->assertSee('11.2');
    }

    /**
     * Le chiffre brut n'atteint le patient qu'apres validation biologique :
     * c'est cette etape qui dit que la valeur est fiable.
     */
    public function test_une_valeur_non_validee_ne_s_affiche_pas(): void
    {
        [$patient, $demande] = $this->analyse();

        $demande->update(['status' => 'available', 'completed_at' => now()]);
        $this->mesurer($demande, 'Hemoglobine', '11.2', validee: false);

        $this->ouvrir($patient)
            ->assertDontSee('11.2')
            ->assertSee('en cours de validation');
    }

    public function test_le_compte_rendu_d_analyses_se_telecharge_en_pdf(): void
    {
        [$patient, $demande] = $this->analyse();
        $demande->update(['status' => 'validated', 'completed_at' => now()]);

        $this->deverrouiller($patient);

        $this->get(route('portal.lab.pdf', [$patient->portal_token, $demande]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_une_analyse_non_rendue_ne_se_telecharge_pas(): void
    {
        [$patient, $demande] = $this->analyse();

        $this->deverrouiller($patient);

        $this->get(route('portal.lab.pdf', [$patient->portal_token, $demande]))->assertNotFound();
    }

    // ------------------------------------------- Comptes rendus d'imagerie

    public function test_un_compte_rendu_d_imagerie_definitif_s_affiche(): void
    {
        [$patient, $demande] = $this->imagerie();

        $demande->report()->create([
            'patient_id' => $demande->patient_id,
            'findings' => 'Foie de taille normale, pas d\'epanchement.',
            'conclusion' => 'Foie de taille normale, pas d\'epanchement.',
            'reported_at' => now(),
            'status' => 'final',
        ]);

        $this->ouvrir($patient)
            ->assertSee('Mes comptes rendus d\'examens (1)', escape: false)
            ->assertSee('Foie de taille normale');
    }

    /**
     * Un brouillon reste au dossier medical : le radiologue le reprend, et ce
     * n'est qu'une fois arrete qu'il devient la parole de l'etablissement.
     */
    public function test_un_compte_rendu_en_brouillon_reste_invisible(): void
    {
        [$patient, $demande] = $this->imagerie();

        $demande->report()->create([
            'patient_id' => $demande->patient_id,
            'findings' => 'Image douteuse a confirmer.',
            'reported_at' => now(),
            'status' => 'draft',
        ]);

        $this->ouvrir($patient)
            ->assertSee('Aucun compte rendu d\'examen', escape: false)
            ->assertDontSee('Image douteuse a confirmer.');
    }

    // -------------------------------------- Documents du dossier medical

    public function test_un_document_du_dossier_medical_s_affiche_et_se_telecharge(): void
    {
        Storage::fake('local');

        $patient = Patient::factory()->create();
        $document = $this->document($patient, 'Compte rendu d\'echographie', 'final');

        // Un titre vient de la base et traverse donc l'echappement de Blade :
        // son apostrophe devient une entite dans le HTML rendu, et la
        // comparaison doit en tenir compte.
        $this->ouvrir($patient)->assertSee('Compte rendu d\'echographie');

        $this->get(route('portal.document', [$patient->portal_token, $document]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_un_document_en_brouillon_n_est_ni_affiche_ni_servi(): void
    {
        Storage::fake('local');

        $patient = Patient::factory()->create();
        $document = $this->document($patient, 'Brouillon de certificat', 'draft');

        $this->ouvrir($patient)->assertDontSee('Brouillon de certificat');

        $this->deverrouiller($patient);

        $this->get(route('portal.document', [$patient->portal_token, $document]))->assertNotFound();
    }

    /**
     * Le cloisonnement compte plus que l'affichage : connaitre l'identifiant
     * d'un document ne doit pas suffire a le lire depuis un autre portail.
     */
    public function test_le_document_d_un_autre_patient_reste_hors_de_portee(): void
    {
        Storage::fake('local');

        $mien = Patient::factory()->create();
        $sien = Patient::factory()->create();
        $document = $this->document($sien, 'Resultat du voisin', 'final');

        $this->deverrouiller($mien);

        $this->get(route('portal.document', [$mien->portal_token, $document]))->assertNotFound();
    }

    public function test_le_telechargement_exige_le_code_valide(): void
    {
        Storage::fake('local');

        $patient = Patient::factory()->create();
        $document = $this->document($patient, 'Compte rendu', 'final');

        $this->get(route('portal.document', [$patient->portal_token, $document]))->assertForbidden();
    }

    // ------------------------------------------------------------ Fixtures

    /**
     * Une demande d'analyses posee depuis /service, comme en vrai : c'est
     * l'action de WorkFlow qui cree le dossier medical au passage.
     *
     * @return array{0: Patient, 1: \Keneya\Dme\Models\LabOrder}
     */
    private function analyse(): array
    {
        $service = Service::factory()->create(['name' => 'Laboratoire']);
        $doctor = $this->makeDoctor($service);
        $visit = $this->makeVisit($service);

        $demande = app(OrderLaboratory::class)->execute($visit, $doctor, [
            'requested_at' => now()->toDateTimeString(),
            'priority' => 'routine',
            'indication' => null,
            'exams' => [['name' => 'Hemogramme']],
        ]);

        return [$visit->patient, $demande->fresh()];
    }

    /**
     * @return array{0: Patient, 1: \Keneya\Dme\Models\ImagingOrder}
     */
    private function imagerie(): array
    {
        $service = Service::factory()->create(['name' => 'Echographie']);
        $doctor = $this->makeDoctor($service);
        $visit = $this->makeVisit($service);

        $demande = app(OrderImaging::class)->execute($visit, $doctor, [
            'modality' => 'ultrasound',
            'body_site' => 'Abdomen',
            'requested_at' => now()->toDateTimeString(),
            'priority' => 'routine',
            'indication' => null,
        ]);

        return [$visit->patient, $demande->fresh()];
    }

    private function mesurer(mixed $demande, string $parametre, string $valeur, bool $validee): LabResult
    {
        return LabResult::create([
            'lab_order_item_id' => $demande->items()->value('id'),
            'patient_id' => $demande->patient_id,
            'parameter' => $parametre,
            'value' => $valeur,
            'unit' => 'g/dL',
            'reference_range' => '12 - 16',
            'flag' => 'low',
            'measured_at' => now(),
            'validated_at' => $validee ? now() : null,
        ]);
    }

    private function document(Patient $patient, string $titre, string $statut): MedicalDocument
    {
        $dossier = PatientProjection::resolve($patient);
        $chemin = 'dme/'.$dossier->getKey().'/'.md5($titre).'.pdf';

        Storage::disk('local')->put($chemin, '%PDF-1.4 contenu');

        return MedicalDocument::create([
            'patient_id' => $dossier->getKey(),
            'title' => $titre,
            'type' => 'imaging_report',
            'disk' => 'local',
            'storage_path' => $chemin,
            'original_name' => 'compte-rendu.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 16,
            'status' => $statut,
        ]);
    }

    /** Le portail ouvert avec le bon code, pret a etre lu. */
    private function ouvrir(Patient $patient): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(PatientPortal::class, ['token' => $patient->portal_token])
            ->set('code', $patient->access_code)
            ->call('unlock')
            ->assertHasNoErrors();
    }

    /** Le meme deverrouillage, pour les routes de telechargement. */
    private function deverrouiller(Patient $patient): void
    {
        $this->withSession(['portal.'.$patient->getKey() => true]);
    }
}
