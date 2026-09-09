<?php

namespace Tests\Feature;

use App\Actions\SendReferral;
use App\Actions\StoreAttachment;
use App\Livewire\Service\IncomingReferrals;
use App\Models\Attachment;
use App\Models\Doctor;
use App\Models\Referral;
use App\Models\Service;
use App\Services\SmsGateway;
use App\Services\SmsSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Keneya\Dme\Models\MedicalDocument;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Point 3 de l'addendum v2 : pieces jointes sur un resultat de renvoi.
 *
 * Les controles de type et de taille sont refaits cote serveur : un formulaire
 * se contourne, pas une action.
 */
class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('attachments');
        $this->mock(SmsGateway::class)->shouldReceive('deliver')->andReturn(SmsSendResult::sent());
    }

    /**
     * Depuis la v3.3.1, le compte rendu d'un renvoi part au **dossier medical**
     * du patient, ou il restera, et non en piece jointe du renvoi, qui n'est
     * qu'un mouvement du parcours. Le prescripteur lit la conclusion dans sa
     * frise ; le fichier, lui, appartient au dossier.
     *
     * Les pieces jointes de WorkFlow demeurent pour ce qui accompagne un
     * passage : elles se deposent depuis « Mes patients » et le dossier.
     */
    public function test_le_compte_rendu_d_un_renvoi_part_au_dossier_medical(): void
    {
        Storage::fake(config('dme.documents.disk', 'local'));

        [$referral, $technicien] = $this->pendingReferral();

        Livewire::actingAs($technicien->user)
            ->test(IncomingReferrals::class, ['serviceId' => $technicien->service_id])
            ->call('startAnswer', $referral->getKey())
            ->set('resultText', 'Echographie sans particularite.')
            ->set('files', [UploadedFile::fake()->image('echographie.jpg')])
            ->call('submitResult')
            ->assertHasNoErrors();

        $document = MedicalDocument::firstOrFail();

        $this->assertSame($technicien->user_id, (int) $document->uploaded_by);
        $this->assertSame(0, Attachment::count(), 'Le compte rendu ne doit pas doubler en piece jointe.');
    }

    public function test_un_type_de_fichier_non_autorise_est_refuse_cote_serveur(): void
    {
        [$referral, $technicien] = $this->pendingReferral();

        $this->expectException(InvalidArgumentException::class);

        // On court-circuite le formulaire : l'action doit refuser d'elle-meme.
        app(StoreAttachment::class)->execute(
            file: UploadedFile::fake()->create('script.php', 10, 'application/x-php'),
            visit: $referral->visit,
            uploadedBy: $technicien->user,
            referral: $referral,
        );
    }

    public function test_un_fichier_trop_volumineux_est_refuse_cote_serveur(): void
    {
        [$referral, $technicien] = $this->pendingReferral();

        $this->expectException(InvalidArgumentException::class);

        app(StoreAttachment::class)->execute(
            file: UploadedFile::fake()->create('scan.pdf', Attachment::MAX_SIZE_KB + 1, 'application/pdf'),
            visit: $referral->visit,
            uploadedBy: $technicien->user,
            referral: $referral,
        );
    }

    public function test_le_formulaire_refuse_aussi_un_type_non_autorise(): void
    {
        [$referral, $technicien] = $this->pendingReferral();

        Livewire::actingAs($technicien->user)
            ->test(IncomingReferrals::class, ['serviceId' => $technicien->service_id])
            ->call('startAnswer', $referral->getKey())
            ->set('resultText', 'Resultat.')
            // Un executable : ni le dossier medical ni WorkFlow n'en veulent.
            ->set('files', [UploadedFile::fake()->create('script.php', 5, 'application/x-php')])
            ->call('submitResult')
            ->assertHasErrors('files.0');

        $this->assertSame(0, MedicalDocument::count());
    }

    public function test_un_medecin_etranger_au_dossier_ne_telecharge_pas_la_piece_jointe(): void
    {
        [$referral, $technicien] = $this->pendingReferral();

        $attachment = app(StoreAttachment::class)->execute(
            file: UploadedFile::fake()->image('resultat.png'),
            visit: $referral->visit,
            uploadedBy: $technicien->user,
            referral: $referral,
        );

        $etranger = $this->makeDoctor(Service::factory()->create());

        $this->actingAs($etranger->user)
            ->get(route('service.attachment', $attachment))
            ->assertForbidden();

        // Le praticien du service concerne y a bien acces.
        $this->actingAs($technicien->user)
            ->get(route('service.attachment', $attachment))
            ->assertOk();
    }

    /**
     * @return array{0: Referral, 1: Doctor}
     */
    private function pendingReferral(): array
    {
        $source = Service::factory()->create();
        $destination = Service::factory()->plateauTechnique()->create();

        $referral = app(SendReferral::class)->execute(
            visit: $this->makeVisit($source),
            fromDoctor: $this->makeDoctor($source),
            toService: $destination,
            instructions: 'Echographie abdominale.',
        );

        return [$referral->refresh()->load('visit'), $this->makeDoctor($destination)];
    }
}
