<?php

namespace Tests\Feature;

use App\Livewire\Service\IncomingReferrals;
use App\Livewire\Service\ServiceQueue;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\Service;
use App\Models\ServiceKind;
use App\Models\Visit;
use App\Services\SmsGateway;
use App\Services\SmsSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Keneya\Dme\Models\ImagingOrder;
use Keneya\Dme\Models\LabOrder;
use Keneya\Dme\Models\MedicalDocument;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La demande d'examen voyage avec le patient (v3.3.1).
 *
 * Demander un examen et envoyer le patient le faire etaient deux gestes sans
 * lien : le medecin remplissait « Examen biologique » dans le dossier medical,
 * puis renvoyait le patient par un autre ecran. Le technicien recevait donc un
 * patient sans savoir ce qu'on lui demandait, et la demande dormait dans le
 * dossier sans destinataire.
 *
 * Le fil conducteur de ces tests est donc la boucle entiere : le medecin
 * envoie et demande d'un seul geste, le technicien voit ce qu'on lui demande,
 * verse son compte rendu au **dossier medical**, et le patient revient dans la
 * file de celui qui l'a envoye.
 */
class ExaminationReferralTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->mock(SmsGateway::class)->shouldReceive('deliver')->andReturn(SmsSendResult::sent());
    }

    /** Un plateau technique qui declare ce qu'il realise. */
    private function plateau(string $nom, ?string $examen): Service
    {
        $kind = ServiceKind::firstOrCreate(
            ['slug' => ServiceKind::SLUG_PLATEAU_TECHNIQUE],
            ['name' => 'Plateau technique'],
        );

        return Service::factory()->create([
            'name' => $nom,
            'service_kind_id' => $kind->getKey(),
            'exam_kind' => $examen,
        ]);
    }

    // ------------------------------------------- Le service dit ce qu'il fait

    public function test_un_service_ne_realise_un_examen_que_s_il_est_un_plateau_technique(): void
    {
        $plateau = $this->plateau('Laboratoire', Service::EXAM_LABORATORY);
        $this->assertSame(Service::EXAM_LABORATORY, $plateau->examKind());
        $this->assertTrue($plateau->ordersLaboratory());

        // Meme colonne, mais un service de consultation : changer le type d'un
        // service ne doit pas laisser un formulaire de demande s'afficher pour
        // une consultation.
        $clinique = Service::factory()->create(['exam_kind' => Service::EXAM_LABORATORY]);
        $this->assertNull($clinique->examKind());
    }

    // ------------------------------------------------- L'aller : un seul geste

    public function test_envoyer_vers_le_laboratoire_pose_la_demande_d_analyses(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $doctor = $this->makeDoctor($service);
        $laboratoire = $this->plateau('Laboratoire', Service::EXAM_LABORATORY);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        Livewire::actingAs($doctor->user)
            ->test(ServiceQueue::class, ['serviceId' => $service->getKey()])
            ->call('startReferral', $visit->getKey())
            ->set('toServiceId', $laboratoire->getKey())
            ->set('examExams.0.name', 'Numeration formule sanguine')
            ->set('examExams.0.category', 'Hematologie')
            ->set('examPriority', 'urgent')
            ->set('instructions', 'Suspicion d\'anemie.')
            ->call('sendReferral')
            ->assertHasNoErrors();

        $demande = LabOrder::query()->latest('id')->first();

        $this->assertNotNull($demande, "La demande d'analyses n'a pas ete posee.");
        $this->assertSame('urgent', $demande->priority);
        $this->assertSame('Numeration formule sanguine', $demande->items->first()->exam_name);

        // Le renvoi la designe : c'est ce lien qui la fait arriver chez le
        // technicien plutot que dormir dans le dossier.
        $referral = Referral::query()->latest('id')->firstOrFail();
        $this->assertSame($demande->getKey(), (int) $referral->dme_lab_order_id);
        $this->assertNull($referral->dme_imaging_order_id);

        // Et le patient est bien parti : c'est la visite qui se deplace.
        $this->assertSame($laboratoire->getKey(), $visit->refresh()->service_id);
    }

    public function test_envoyer_vers_l_echographie_pose_la_demande_d_imagerie(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);
        $echographie = $this->plateau('Echographie', Service::EXAM_IMAGING);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        Livewire::actingAs($doctor->user)
            ->test(ServiceQueue::class, ['serviceId' => $service->getKey()])
            ->call('startReferral', $visit->getKey())
            ->set('toServiceId', $echographie->getKey())
            ->set('examModality', 'ultrasound')
            ->set('examBodySite', 'Abdomen')
            ->set('instructions', 'Douleur du flanc droit.')
            ->call('sendReferral')
            ->assertHasNoErrors();

        $demande = ImagingOrder::query()->latest('id')->firstOrFail();

        $this->assertSame('ultrasound', $demande->modality);
        $this->assertSame('Abdomen', $demande->body_site);
        $this->assertSame(
            $demande->getKey(),
            (int) Referral::query()->latest('id')->firstOrFail()->dme_imaging_order_id,
        );
    }

    /**
     * Un renvoi vers un service qui ne realise pas d'examen part seul, comme
     * avant : le circuit interservice n'est pas reserve aux plateaux.
     */
    public function test_un_renvoi_ordinaire_part_sans_demande(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);
        $autre = Service::factory()->create(['name' => 'Urgences']);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        Livewire::actingAs($doctor->user)
            ->test(ServiceQueue::class, ['serviceId' => $service->getKey()])
            ->call('startReferral', $visit->getKey())
            ->set('toServiceId', $autre->getKey())
            ->set('instructions', 'Avis urgent.')
            ->call('sendReferral')
            ->assertHasNoErrors();

        $referral = Referral::query()->latest('id')->firstOrFail();

        $this->assertFalse($referral->carriesExamination());
        $this->assertSame(0, LabOrder::count());
        $this->assertSame(0, ImagingOrder::count());
    }

    /**
     * Une demande d'analyses sans aucune analyse ne veut rien dire, et le
     * patient ne doit pas partir pour autant.
     */
    public function test_une_demande_d_analyses_vide_est_refusee_et_le_patient_reste(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);
        $laboratoire = $this->plateau('Laboratoire', Service::EXAM_LABORATORY);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        Livewire::actingAs($doctor->user)
            ->test(ServiceQueue::class, ['serviceId' => $service->getKey()])
            ->call('startReferral', $visit->getKey())
            ->set('toServiceId', $laboratoire->getKey())
            ->set('instructions', 'Bilan.')
            ->call('sendReferral')
            ->assertHasErrors('examExams');

        $this->assertSame(0, Referral::count());
        $this->assertSame($service->getKey(), $visit->refresh()->service_id);
    }

    // --------------------------------------------- Le retour : dossier et file

    /**
     * La boucle entiere : le technicien voit la demande, verse son compte rendu
     * au dossier medical, et le patient revient chez son medecin.
     */
    public function test_le_technicien_verse_son_compte_rendu_au_dossier_et_le_patient_revient(): void
    {
        Storage::fake(config('dme.documents.disk', 'local'));

        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $doctor = $this->makeDoctor($service);
        $echographie = $this->plateau('Echographie', Service::EXAM_IMAGING);
        $technicien = $this->makeDoctor($echographie);

        $patient = Patient::factory()->create(['name' => 'Fode Drame']);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED], $patient);

        Livewire::actingAs($doctor->user)
            ->test(ServiceQueue::class, ['serviceId' => $service->getKey()])
            ->call('startReferral', $visit->getKey())
            ->set('toServiceId', $echographie->getKey())
            ->set('examModality', 'ultrasound')
            ->set('examBodySite', 'Abdomen')
            ->set('instructions', 'Douleur du flanc droit.')
            ->call('sendReferral')
            ->assertHasNoErrors();

        $referral = Referral::query()->latest('id')->firstOrFail();

        // Le technicien voit ce qu'on lui demande, sans changer d'ecran.
        Livewire::actingAs($technicien->user)
            ->test(IncomingReferrals::class, ['serviceId' => $echographie->getKey()])
            ->assertSee($referral->imagingOrder->order_number)
            ->assertSee('Abdomen');

        Livewire::actingAs($technicien->user)
            ->test(IncomingReferrals::class, ['serviceId' => $echographie->getKey()])
            ->call('startAnswer', $referral->getKey())
            ->set('resultText', 'Foie et voies biliaires sans particularite.')
            ->set('documentTitle', 'Compte rendu d\'echographie')
            ->set('files', [UploadedFile::fake()->create('compte-rendu.pdf', 40, 'application/pdf')])
            ->call('submitResult')
            ->assertHasNoErrors();

        // 1. Le compte rendu est au dossier medical, classe pour ce qu'il est.
        $document = MedicalDocument::query()->latest('id')->first();

        $this->assertNotNull($document, "Le compte rendu n'est pas arrive au dossier medical.");
        $this->assertSame('imaging_report', $document->type);
        $this->assertSame('Compte rendu d\'echographie', $document->title);

        // 2. La demande cesse d'etre en attente.
        $this->assertSame('reported', $referral->imagingOrder()->first()->status);

        // 3. Le patient revient dans la file de celui qui l'a envoye.
        $this->assertSame(Referral::STATUS_DONE, $referral->refresh()->status);
        $this->assertSame($service->getKey(), $visit->refresh()->service_id);
        $this->assertSame(Visit::STATUS_WAITING, $visit->status);
    }

    /**
     * Un resultat d'analyses se classe comme tel : un compte rendu de
     * laboratoire ne se range pas avec une echographie.
     */
    public function test_le_compte_rendu_prend_la_nature_de_la_demande(): void
    {
        Storage::fake(config('dme.documents.disk', 'local'));

        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);
        $laboratoire = $this->plateau('Laboratoire', Service::EXAM_LABORATORY);
        $technicien = $this->makeDoctor($laboratoire);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        Livewire::actingAs($doctor->user)
            ->test(ServiceQueue::class, ['serviceId' => $service->getKey()])
            ->call('startReferral', $visit->getKey())
            ->set('toServiceId', $laboratoire->getKey())
            ->set('examExams.0.name', 'Glycemie a jeun')
            ->set('instructions', 'Bilan.')
            ->call('sendReferral')
            ->assertHasNoErrors();

        $referral = Referral::query()->latest('id')->firstOrFail();

        Livewire::actingAs($technicien->user)
            ->test(IncomingReferrals::class, ['serviceId' => $laboratoire->getKey()])
            ->call('startAnswer', $referral->getKey())
            ->set('resultText', 'Glycemie a 0,92 g/l.')
            ->set('files', [UploadedFile::fake()->create('resultat.pdf', 20, 'application/pdf')])
            ->call('submitResult')
            ->assertHasNoErrors();

        $this->assertSame('lab_result', MedicalDocument::query()->latest('id')->firstOrFail()->type);
        $this->assertSame('available', $referral->labOrder()->first()->status);
    }

    /**
     * Le compte rendu est facultatif : une machine en panne ne doit pas
     * empecher de rendre une conclusion, ni retenir le patient au plateau.
     */
    public function test_un_resultat_sans_fichier_ramene_quand_meme_le_patient(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);
        $laboratoire = $this->plateau('Laboratoire', Service::EXAM_LABORATORY);
        $technicien = $this->makeDoctor($laboratoire);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        Livewire::actingAs($doctor->user)
            ->test(ServiceQueue::class, ['serviceId' => $service->getKey()])
            ->call('startReferral', $visit->getKey())
            ->set('toServiceId', $laboratoire->getKey())
            ->set('examExams.0.name', 'Glycemie a jeun')
            ->set('instructions', 'Bilan.')
            ->call('sendReferral')
            ->assertHasNoErrors();

        $referral = Referral::query()->latest('id')->firstOrFail();

        Livewire::actingAs($technicien->user)
            ->test(IncomingReferrals::class, ['serviceId' => $laboratoire->getKey()])
            ->call('startAnswer', $referral->getKey())
            ->set('resultText', 'Analyse non realisable : automate en panne.')
            ->call('submitResult')
            ->assertHasNoErrors();

        $this->assertSame(0, MedicalDocument::count());
        $this->assertSame($service->getKey(), $visit->refresh()->service_id);
    }
}
