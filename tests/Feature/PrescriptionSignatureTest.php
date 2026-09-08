<?php

namespace Tests\Feature;

use App\Actions\Dme\CreateMedicalPrescription;
use App\Actions\StoreSignatureImage;
use App\Livewire\Admin\HospitalSettings;
use App\Livewire\Shared\ProfileCard;
use App\Models\Doctor;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Visit;
use App\Support\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Keneya\Dme\Dme;
use Keneya\Dme\Models\Prescription;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Signature, tampons et en-tete d'ordonnance (v3.2.9, point 2).
 *
 * Le fil conducteur : ces trois images ont une valeur legale, mais leur
 * absence ne doit jamais empecher d'imprimer une ordonnance.
 */
class PrescriptionSignatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        Storage::fake('signatures');
    }

    /**
     * L'ordonnance vit dans le dossier medical depuis la v3.3.1 : c'est ce
     * document-la qui porte la signature et les cachets.
     */
    private function makePrescription(Doctor $doctor, Visit $visit): Prescription
    {
        return app(CreateMedicalPrescription::class)->execute($visit, $doctor, [
            ['medicament' => 'Paracetamol 500 mg', 'posologie' => '3 fois par jour', 'duree' => '5 jours'],
        ]);
    }

    // --------------------------------------------- L'absence n'empeche rien

    /**
     * La verification demandee : sans signature, sans tampon medecin et sans
     * tampon d'etablissement, l'ordonnance s'imprime normalement.
     */
    public function test_une_ordonnance_sans_aucune_image_s_imprime_normalement(): void
    {
        $service = Service::factory()->create();
        $medecin = $this->makeDoctor($service);
        $visit = $this->makeVisit($service);
        $ordonnance = $this->makePrescription($medecin, $visit);

        $signatures = Dme::signaturesFor($ordonnance);

        $this->assertNull($signatures['doctorSignature']);
        $this->assertNull($signatures['doctorStamp']);
        $this->assertNull($signatures['facilityStamp']);

        $this->actingAs($medecin->user)
            ->get(route('service.prescription.pdf', $ordonnance))
            ->assertOk();
    }

    /**
     * Un chemin enregistre dont le fichier a disparu ne doit pas faire echouer
     * la generation : c'est le cas qui casserait dompdf sans ce garde-fou.
     */
    public function test_un_chemin_dont_le_fichier_a_disparu_laisse_l_espace_vide(): void
    {
        $service = Service::factory()->create();
        $medecin = $this->makeDoctor($service);
        $medecin->forceFill(['signature_path' => 'medecins/1/disparue.png'])->save();
        Setting::put(Setting::HOSPITAL_STAMP_PATH, 'etablissement/disparu.png');

        $visit = $this->makeVisit($service);
        $ordonnance = $this->makePrescription($medecin, $visit);

        $signatures = Dme::signaturesFor($ordonnance);

        $this->assertNull($signatures['doctorSignature']);
        $this->assertNull($signatures['facilityStamp']);

        $this->actingAs($medecin->user)
            ->get(route('service.prescription.pdf', $ordonnance))
            ->assertOk();
    }

    // --------------------------------------------- En-tete configurable

    public function test_l_en_tete_reprend_les_coordonnees_reglees_dans_l_administration(): void
    {
        Setting::put(Setting::HOSPITAL_ADDRESS, 'Quartier Legal Segou, Kayes');
        Setting::put(Setting::HOSPITAL_PHONE, '+223 21 52 00 00');
        Setting::put(Setting::HOSPITAL_EMAIL, 'contact@hfd.ml');

        $service = Service::factory()->create();
        $medecin = $this->makeDoctor($service);
        $ordonnance = $this->makePrescription($medecin, $this->makeVisit($service));

        $this->assertNotNull($ordonnance);

        $etablissement = Dme::facility();

        $this->assertSame('Quartier Legal Segou, Kayes', $etablissement['address']);
        $this->assertSame('+223 21 52 00 00', $etablissement['phone']);
        $this->assertSame('contact@hfd.ml', $etablissement['email']);
    }

    public function test_l_administration_enregistre_les_coordonnees(): void
    {
        Livewire::actingAs($this->makeAdmin())
            ->test(HospitalSettings::class)
            ->set('hospitalName', 'Hopital Fousseyni Daou')
            ->set('hospitalAddress', 'Quartier Legal Segou, Kayes')
            ->set('hospitalPhone', '+223 21 52 00 00')
            ->set('hospitalEmail', 'contact@hfd.ml')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('contact@hfd.ml', Setting::get(Setting::HOSPITAL_EMAIL));
    }

    // --------------------------------------------- La fusion des deux ordonnances

    /**
     * Le coeur du §4 du chantier v3.3.1 : l'ordonnance prend la forme du
     * dossier medical, et garde la fonction que WorkFlow avait seul —
     * signature du prescripteur, son cachet, celui de l'etablissement.
     */
    public function test_l_imprime_porte_la_signature_le_cachet_du_medecin_et_celui_de_l_hopital(): void
    {
        $service = Service::factory()->create();
        $medecin = $this->makeDoctor($service);

        $depot = app(StoreSignatureImage::class);
        $depot->forDoctorSignature(UploadedFile::fake()->image('signature.png'), $medecin);
        $depot->forDoctorStamp(UploadedFile::fake()->image('tampon.png'), $medecin->fresh());
        $depot->forHospitalStamp(UploadedFile::fake()->image('etablissement.png'));

        $medecin->refresh();
        $ordonnance = $this->makePrescription($medecin, $this->makeVisit($service));

        $signatures = Dme::signaturesFor($ordonnance);

        $this->assertNotNull($signatures['doctorSignature']);
        $this->assertNotNull($signatures['doctorStamp']);
        $this->assertNotNull($signatures['facilityStamp']);

        // Les images doivent atteindre le papier : dompdf lit le disque, le
        // navigateur ne le peut pas, et l'imprime est ce que le medecin
        // signe. Chacune des trois doit donc figurer, encodee, sur la page
        // imprimable — pas seulement etre trouvee sur le disque.
        $rendu = $this->actingAs($medecin->user)
            ->get(route('service.prescription.print', $ordonnance))
            ->assertOk()
            ->getContent();

        foreach ($signatures as $role => $chemin) {
            $this->assertStringContainsString(
                'data:image/png;base64,'.base64_encode((string) file_get_contents($chemin)),
                $rendu,
                "L'image « {$role} » manque a l'ordonnance imprimable.",
            );
        }

        $this->actingAs($medecin->user)
            ->get(route('service.prescription.pdf', $ordonnance))
            ->assertOk();
    }

    /**
     * Un medecin rattache a deux services n'a depose ses images que sur une
     * fiche : l'ordonnance, qui ne designe que son compte, doit tout de meme
     * les retrouver.
     */
    public function test_la_signature_se_retrouve_quel_que_soit_le_rattachement(): void
    {
        $premier = Service::factory()->create();
        $second = Service::factory()->create();

        $medecin = $this->makeDoctor($premier);
        $autreFiche = Doctor::create(['user_id' => $medecin->user_id, 'service_id' => $second->getKey()]);

        app(StoreSignatureImage::class)
            ->forDoctorSignature(UploadedFile::fake()->image('signature.png'), $autreFiche);

        $ordonnance = $this->makePrescription($medecin, $this->makeVisit($premier));

        $this->assertNotNull(Dme::signaturesFor($ordonnance)['doctorSignature']);
    }

    // --------------------------------------------- Depot et securite

    public function test_le_medecin_depose_sa_signature_et_son_tampon(): void
    {
        $service = Service::factory()->create();
        $medecin = $this->makeDoctor($service);

        Livewire::actingAs($medecin->user)
            ->test(ProfileCard::class)
            ->call('startSignatureChange')
            ->set('signatureFile', UploadedFile::fake()->image('signature.png'))
            ->set('stampFile', UploadedFile::fake()->image('tampon.png'))
            ->call('saveSignature')
            ->assertHasNoErrors();

        $medecin->refresh();

        $this->assertNotNull($medecin->signature_path);
        $this->assertNotNull($medecin->stamp_path);
        Storage::disk('signatures')->assertExists($medecin->signature_path);
    }

    /** Une image remplacee disparait du disque : une signature perimee reste utilisable. */
    public function test_l_image_remplacee_est_effacee_du_disque(): void
    {
        $medecin = $this->makeDoctor(Service::factory()->create());
        $action = app(StoreSignatureImage::class);

        $premier = $action->forDoctorSignature(UploadedFile::fake()->image('une.png'), $medecin);
        $action->forDoctorSignature(UploadedFile::fake()->image('deux.png'), $medecin->fresh());

        Storage::disk('signatures')->assertMissing($premier);
    }

    /** Un fichier qui n'est pas une image est refuse cote serveur, pas seulement au formulaire. */
    public function test_un_fichier_non_image_est_refuse_par_l_action(): void
    {
        $medecin = $this->makeDoctor(Service::factory()->create());

        $this->expectException(InvalidArgumentException::class);

        app(StoreSignatureImage::class)->forDoctorSignature(
            UploadedFile::fake()->create('ordonnance.pdf', 10, 'application/pdf'),
            $medecin,
        );
    }

    public function test_une_image_trop_lourde_est_refusee(): void
    {
        $medecin = $this->makeDoctor(Service::factory()->create());

        $this->expectException(InvalidArgumentException::class);

        app(StoreSignatureImage::class)->forDoctorSignature(
            UploadedFile::fake()->image('enorme.png')->size(3000),
            $medecin,
        );
    }

    /**
     * La verification demandee : tout changement sur l'une des trois images
     * apparait au journal d'audit.
     */
    public function test_chaque_changement_d_image_est_journalise(): void
    {
        $medecin = $this->makeDoctor(Service::factory()->create());
        $action = app(StoreSignatureImage::class);

        $this->actingAs($medecin->user);
        $action->forDoctorSignature(UploadedFile::fake()->image('signature.png'), $medecin);
        $action->forDoctorStamp(UploadedFile::fake()->image('tampon.png'), $medecin->fresh());

        $this->actingAs($this->makeAdmin());
        $action->forHospitalStamp(UploadedFile::fake()->image('cachet.png'));

        $traces = Activity::where('event', Audit::EVENT_SIGNATURE_CHANGED)->get();

        $this->assertCount(3, $traces);
        $this->assertTrue($traces->contains(fn ($t) => str_contains($t->description, "Tampon de l'etablissement")));
    }

    /** Le tampon institutionnel ne se regle que depuis /admin. */
    public function test_le_tampon_de_l_etablissement_se_depose_depuis_l_administration(): void
    {
        Livewire::actingAs($this->makeAdmin())
            ->test(HospitalSettings::class)
            ->set('stampFile', UploadedFile::fake()->image('cachet.png'))
            ->call('saveStamp')
            ->assertHasNoErrors();

        $chemin = Setting::get(Setting::HOSPITAL_STAMP_PATH);

        $this->assertNotNull($chemin);
        Storage::disk('signatures')->assertExists($chemin);
    }
}
