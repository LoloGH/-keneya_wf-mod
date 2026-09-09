<?php

namespace Tests\Feature;

use App\Actions\AdmitPatient;
use App\Actions\PrescribeCareTasks;
use App\Actions\ReviseCareTask;
use App\Livewire\Reception\PatientRegistrationForm;
use App\Livewire\Reception\VisitorRegistrationForm;
use App\Livewire\Service\ConsultationActions;
use App\Livewire\Service\Hospitalizations;
use App\Livewire\Service\MedicalBackground;
use App\Livewire\Service\MedicalConsultation;
use App\Livewire\Service\MedicalOrders;
use App\Models\CareTask;
use App\Models\CareTaskType;
use App\Models\Patient;
use App\Models\Service;
use App\Models\StaffMember;
use App\Models\StaffType;
use App\Models\User;
use App\Models\Visit;
use App\Services\SmsGateway;
use App\Services\SmsSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Keneya\Dme\Models\Consultation;
use Keneya\Dme\Models\ImagingOrder;
use Keneya\Dme\Models\Prescription;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Toute capacite cochee produit quelque chose sur /staff/{slug} (v3.3.1).
 *
 * Le defaut corrige ici etait entier et silencieux : quatorze des vingt et une
 * capacites n'avaient aucune section sur l'interface dediee. L'administrateur
 * cochait les vingt et une cases d'un type « Echographie », la colonne
 * « Fonctions » affichait 21 / 21, et l'echographiste se connectait sur une
 * interface qui ne savait ni rediger une consultation, ni demander une
 * imagerie, ni etablir une ordonnance. Rien ne signalait l'ecart : l'apercu
 * annoncait des sections que la page ne construisait pas.
 *
 * D'ou un test qui part de la table des capacites elle-meme plutot que d'une
 * liste recopiee : une capacite ajoutee sans section le fera echouer.
 */
class StaffDedicatedInterfaceCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(SmsGateway::class)->shouldReceive('deliver')->andReturn(SmsSendResult::sent());
    }

    /**
     * Un type generique portant toutes les capacites, et la personne qui
     * l'exerce — le cas de la capture d'ecran qui a revele le defaut.
     *
     * @return array{0: StaffType, 1: User, 2: Service}
     */
    private function echographiste(): array
    {
        $service = Service::factory()->create(['name' => 'Echographie']);

        $type = StaffType::create([
            'name' => 'Echographie',
            'matched_role' => null,
            'slug' => StaffType::makeSlug('Echographie'),
            'capabilities' => array_keys(StaffType::CAPABILITIES),
        ]);

        $user = User::factory()->create(['name' => 'Amadou Cisse']);

        StaffMember::create([
            'user_id' => $user->getKey(),
            'staff_type_id' => $type->getKey(),
            'service_id' => $service->getKey(),
        ]);

        return [$type, $user, $service];
    }

    private function visiteAppelee(Service $service): Visit
    {
        $patient = Patient::factory()->create(['name' => 'Fode Drame']);

        return $this->makeVisit($service, ['status' => Visit::STATUS_CALLED], $patient);
    }

    // ------------------------------------------------------ La barre est pleine

    /**
     * La verification demandee : toutes les cases cochees, toutes les sections
     * presentes. Les libelles attendus sont ceux de la barre de navigation.
     */
    public function test_toutes_les_capacites_cochees_donnent_toutes_les_sections(): void
    {
        [$type, $user] = $this->echographiste();

        $reponse = $this->actingAs($user)->get('/staff/'.$type->slug)->assertOk();

        foreach ([
            "File d'attente",
            'Dossier medical',
            'Consultation',
            'Antecedents et allergies',
            'Traitements et examens',
            'Renvois recus',
            'Fin de consultation',
            'Patients hospitalises',
            'Soins programmes',
            'Releves',
            'Mes patients',
            'Mes rendez-vous',
            'Nouveau patient',
            'Visiteur',
            'Encaissement',
            'Signaler un constat',
            'Mon planning',
        ] as $section) {
            $reponse->assertSee($section);
        }
    }

    /**
     * Le filet : aucune capacite ne doit rester sans effet sur l'interface.
     *
     * On coche tout, puis on decoche une capacite a la fois — la page doit
     * changer. Une capacite dont le retrait ne change rien est une case qui
     * ment a l'administrateur.
     */
    public function test_aucune_capacite_ne_reste_sans_effet_sur_l_interface(): void
    {
        [$type, $user] = $this->echographiste();

        $complete = $this->actingAs($user)->get('/staff/'.$type->slug)->assertOk()->getContent();

        foreach (array_keys(StaffType::CAPABILITIES) as $capacite) {
            $type->update([
                'capabilities' => array_values(array_diff(array_keys(StaffType::CAPABILITIES), [$capacite])),
            ]);

            $ampute = $this->actingAs($user)->get('/staff/'.$type->slug)->assertOk()->getContent();

            $this->assertNotSame(
                $complete,
                $ampute,
                sprintf('Decocher « %s » ne change rien sur /staff.', StaffType::CAPABILITIES[$capacite]['label']),
            );
        }
    }

    // ------------------------------------------- Les ecrans ecrivent vraiment

    /**
     * Le coeur de la consigne : ce que saisit un personnel autorise atterrit
     * dans le dossier medical, comme pour un medecin — et sans qu'il ait les
     * droits d'ouvrir le DME.
     */
    public function test_un_personnel_generique_redige_une_consultation_dans_le_dme(): void
    {
        [$type, $user, $service] = $this->echographiste();

        // Sans la capacite d'ouvrir le dossier medical complet : consigner un
        // acte et lire un dossier ne sont pas le meme droit.
        $type->update([
            'capabilities' => array_values(array_diff(
                array_keys(StaffType::CAPABILITIES),
                [StaffType::CAP_ACCESS_DME],
            )),
        ]);

        $visit = $this->visiteAppelee($service);

        $this->assertFalse($user->fresh()->canAccessDme());

        Livewire::actingAs($user)
            ->test(MedicalConsultation::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('reason', 'Douleur abdominale')
            ->call('save')
            ->assertHasNoErrors();

        $consultation = Consultation::query()->latest('id')->first();

        $this->assertNotNull($consultation, "La consultation n'est pas arrivee dans le dossier medical.");
        $this->assertSame('Douleur abdominale', $consultation->reason);
        // Le dossier medical designe des comptes, non des fiches de service :
        // l'acte est bien signe par l'echographiste.
        $this->assertSame($user->getKey(), (int) $consultation->doctor_id);
    }

    public function test_un_personnel_generique_demande_une_imagerie(): void
    {
        [, $user, $service] = $this->echographiste();
        $visit = $this->visiteAppelee($service);

        Livewire::actingAs($user)
            ->test(MedicalOrders::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('modality', 'ultrasound')
            ->set('bodySite', 'Abdomen')
            ->call('saveImagingOrder')
            ->assertHasNoErrors();

        $demande = ImagingOrder::query()->latest('id')->first();

        $this->assertNotNull($demande, "La demande d'imagerie n'est pas arrivee dans le dossier medical.");
        $this->assertSame($user->getKey(), (int) $demande->doctor_id);
    }

    public function test_un_personnel_generique_consigne_une_allergie(): void
    {
        [, $user, $service] = $this->echographiste();
        $visit = $this->visiteAppelee($service);

        Livewire::actingAs($user)
            ->test(MedicalBackground::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('allergen', 'Penicilline')
            ->set('severity', 'severe')
            ->call('saveAllergy')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('dme_allergies', [
            'allergen' => 'Penicilline',
            'recorded_by' => $user->getKey(),
        ]);
    }

    /**
     * L'ordonnance fusionnee, ecrite depuis un poste dedie : meme table, meme
     * document, seule la colonne du signataire change.
     */
    public function test_un_personnel_generique_etablit_une_ordonnance(): void
    {
        [, $user, $service] = $this->echographiste();
        $visit = $this->visiteAppelee($service);

        Livewire::actingAs($user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('prescriptionLines.0.medicament', 'Paracetamol 500 mg')
            ->call('savePrescription')
            ->assertHasNoErrors();

        $ordonnance = Prescription::query()->latest('id')->first();

        $this->assertNotNull($ordonnance, "L'ordonnance n'est pas arrivee dans le dossier medical.");
        $this->assertSame($user->getKey(), (int) $ordonnance->doctor_id);
    }

    /**
     * Le rendez-vous et l'admission signent `staff_member_id`, jamais
     * `doctor_id` : on ne fabrique pas de faux medecins dans un dossier.
     */
    public function test_le_rendez_vous_est_signe_par_le_personnel_et_non_par_un_medecin(): void
    {
        [, $user, $service] = $this->echographiste();
        $visit = $this->visiteAppelee($service);

        Livewire::actingAs($user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('appointmentAt', now()->addWeek()->format('Y-m-d\TH:i'))
            ->call('saveAppointment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $visit->patient_id,
            'doctor_id' => null,
            'staff_member_id' => $user->staffMember->getKey(),
        ]);
    }

    // ----------------------------------------- Les capacites gardent le metier

    /**
     * Les formulaires d'accueil sont desormais atteignables depuis un poste
     * dedie : masquer la section ne suffit pas, un composant Livewire s'appelle
     * sans passer par le menu.
     */
    public function test_l_enregistrement_hors_capacite_est_refuse_cote_serveur(): void
    {
        [$type, $user] = $this->echographiste();

        $type->update(['capabilities' => [StaffType::CAP_QUEUE]]);

        Livewire::actingAs($user)
            ->test(PatientRegistrationForm::class)
            ->call('save')
            ->assertForbidden();

        Livewire::actingAs($user)
            ->test(VisitorRegistrationForm::class)
            ->call('save')
            ->assertForbidden();
    }

    /**
     * Prescrire un soin est un acte attribuable (v3.3.1) : un poste dedie qui
     * porte la capacite prescrit, et le soin est signe par lui — jamais par un
     * medecin invente.
     */
    public function test_un_personnel_generique_prescrit_des_soins(): void
    {
        [, $user, $service] = $this->echographiste();

        $visit = $this->visiteAppelee($service);
        $sejour = app(AdmitPatient::class)->execute($visit, $user->staffMember);
        $type = CareTaskType::create(['name' => 'Pansement']);

        Livewire::actingAs($user)
            ->test(Hospitalizations::class, ['serviceId' => $service->getKey()])
            ->call('startPrescription', $sejour->getKey())
            ->set('careTaskTypeId', $type->getKey())
            ->set('careStartsAt', now()->addHour()->format('Y-m-d\TH:i'))
            ->set('careIntervalHours', 8)
            ->set('careDurationDays', 1)
            ->call('prescribe')
            ->assertHasNoErrors();

        $soin = CareTask::query()->latest('id')->first();

        $this->assertNotNull($soin, "Le soin n'a pas ete prescrit.");
        $this->assertNull($soin->prescribed_by_doctor_id, 'Un faux medecin a ete inscrit au dossier.');
        $this->assertSame($user->staffMember->getKey(), (int) $soin->prescribed_by_staff_member_id);
        $this->assertSame('Amadou Cisse', $soin->prescribedByName());
    }

    /**
     * La capacite se coche a part : un compte qui admet sans porter la
     * prescription ne voit pas le bouton, et ne peut pas l'appeler.
     */
    public function test_prescrire_un_soin_hors_capacite_est_refuse(): void
    {
        [$type, $user, $service] = $this->echographiste();

        $type->update([
            'capabilities' => array_values(array_diff(
                array_keys(StaffType::CAPABILITIES),
                [StaffType::CAP_PRESCRIBE_CARE],
            )),
        ]);

        $this->actingAs($user)
            ->get('/staff/'.$type->slug)
            ->assertOk()
            ->assertDontSee('Prescrire des soins');

        Livewire::actingAs($user)
            ->test(Hospitalizations::class, ['serviceId' => $service->getKey()])
            ->call('prescribe')
            ->assertForbidden();
    }

    /**
     * Le prescripteur peut revenir sur son propre soin, qu'il soit medecin ou
     * non : la regle compare des comptes, non des fiches de service.
     */
    public function test_le_prescripteur_generique_peut_corriger_son_soin(): void
    {
        [, $user, $service] = $this->echographiste();

        $visit = $this->visiteAppelee($service);
        $sejour = app(AdmitPatient::class)->execute($visit, $user->staffMember);
        $type = CareTaskType::create(['name' => 'Pansement']);

        app(PrescribeCareTasks::class)->execute(
            hospitalization: $sejour,
            type: $type,
            doctor: $user->staffMember,
            start: now()->addHour(),
            intervalHours: 8,
            durationDays: 1,
        );

        $soin = CareTask::query()->latest('id')->first();

        app(ReviseCareTask::class)->cancel($soin, $user->fresh(), 'Erreur de saisie.');

        $this->assertSame(CareTask::STATUS_CANCELLED, $soin->fresh()->status);
    }
}
