<?php

namespace Tests\Feature;

use App\Livewire\Admin\ActivityLogViewer;
use App\Livewire\Service\MyPatients;
use App\Models\Patient;
use App\Models\PatientHistory;
use App\Models\Service;
use App\Models\SmsMessage;
use App\Models\StaffType;
use App\Models\User;
use App\Services\Dme\WorkflowSmsDispatcher;
use App\Services\SmsGateway;
use App\Services\SmsSendResult;
use App\Support\Roles;
use Database\Seeders\DmePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Keneya\Dme\Contracts\SmsDispatcherContract;
use Keneya\Dme\Models\Consultation;
use Keneya\Dme\Models\Patient as DmePatient;
use Keneya\Dme\Sms\SmsContext;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Montage du module Dossier Medical Electronique dans WorkFlow (v3.3.0).
 *
 * Ces tests ne verifient pas le DME — il a sa propre suite — mais les six
 * jointures entre les deux applications, celles qui n'existent qu'une fois
 * assemblees et que ni l'une ni l'autre ne couvre seule :
 *
 *   1. la capacite `can_access_dme` fait apparaitre l'action, ou non ;
 *   2. une URL tapee a la main est soumise a la meme regle ;
 *   3. on entre dans le module sans se reauthentifier ;
 *   4. un patient WorkFlow obtient son dossier medical au premier acces ;
 *   5. un SMS du module part par la file de WorkFlow ;
 *   6. une action posee dans le module se lit dans le journal de /admin.
 */
class DmeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->seed(DmePermissionSeeder::class);
    }

    /**
     * Un medecin rattache a un service, dont le type de personnel porte — ou
     * non — la capacite d'ouvrir le dossier medical.
     *
     * @return array{0: User, 1: Patient}
     */
    private function medecinEtSonPatient(bool $avecDme): array
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $doctor = $this->makeDoctor($service);

        $type = $this->staffTypeFor(Roles::DOCTOR);
        $type->update(['capabilities' => $avecDme ? [StaffType::CAP_ACCESS_DME] : []]);

        $patient = Patient::factory()->create([
            'name' => 'Aminata Traore',
            'gender' => 'Femme',
            'age' => 34,
            'mobile' => '76000000',
        ]);

        // « Mes patients » liste les patients dont ce medecin porte une trace
        // de prise en charge : sans elle, la liste serait vide.
        PatientHistory::create([
            'patient_id' => $patient->getKey(),
            'type' => PatientHistory::TYPE_CONSULTATION,
            'service_id' => $service->getKey(),
            'doctor_id' => $doctor->getKey(),
            'description' => 'Consultation initiale.',
        ]);

        return [$doctor->user, $patient];
    }

    // ------------------------------------------------------ 1 et 2. Acces

    public function test_l_action_dossier_medical_n_apparait_qu_avec_la_capacite(): void
    {
        [$medecin] = $this->medecinEtSonPatient(avecDme: true);

        Livewire::actingAs($medecin)
            ->test(MyPatients::class)
            ->assertSee('Dossier medical complet');
    }

    public function test_un_medecin_sans_la_capacite_ne_voit_pas_l_action(): void
    {
        [$medecin] = $this->medecinEtSonPatient(avecDme: false);

        Livewire::actingAs($medecin)
            ->test(MyPatients::class)
            ->assertDontSee('Dossier medical complet');
    }

    public function test_une_url_directe_est_refusee_sans_la_capacite(): void
    {
        [$medecin, $patient] = $this->medecinEtSonPatient(avecDme: false);

        // Refus, mais pas une page d'erreur : le medecin repart vers son
        // espace avec une phrase qui explique pourquoi.
        $this->actingAs($medecin)
            ->get(route('dossier-medical.ouvrir', $patient))
            ->assertRedirect(route('service.home'));

        $this->assertDatabaseCount('dme_patients', 0);
    }

    public function test_le_module_refuse_aussi_ses_propres_urls_sans_la_capacite(): void
    {
        [$medecin] = $this->medecinEtSonPatient(avecDme: false);

        // La porte du module (`dme.access`) rend le meme verdict que
        // l'interface : c'est HostAccessGate qui interroge WorkFlow.
        $this->actingAs($medecin)->get(route('dme.home'))->assertForbidden();
    }

    // -------------------------------------------- 3. Pas de seconde session

    public function test_l_entree_dans_le_module_ne_redemande_pas_de_mot_de_passe(): void
    {
        [$medecin, $patient] = $this->medecinEtSonPatient(avecDme: true);

        $entree = $this->actingAs($medecin)->get(route('dossier-medical.ouvrir', $patient));

        $dossier = DmePatient::firstOrFail();
        $entree->assertRedirect(route('dme.patients.show', $dossier));

        // La page du module s'ouvre sous la meme session : ni page de
        // connexion, ni redirection vers l'authentification.
        $this->actingAs($medecin)
            ->get(route('dme.patients.show', $dossier))
            ->assertOk()
            ->assertSee('Aminata');

        // Et l'ouverture est tracee au nom du compte WorkFlow : c'est la
        // meme personne des deux cotes, pas un compte technique du module.
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'medical',
            'action' => 'viewed',
            'causer_id' => $medecin->getKey(),
            'patient_id' => $dossier->getKey(),
        ]);
    }

    // ------------------------------------- 4. Creation du dossier au besoin

    public function test_un_patient_sans_dossier_medical_en_obtient_un_au_premier_acces(): void
    {
        [$medecin, $patient] = $this->medecinEtSonPatient(avecDme: true);

        $this->assertDatabaseCount('dme_patients', 0);

        $this->actingAs($medecin)->get(route('dossier-medical.ouvrir', $patient));

        $this->assertDatabaseCount('dme_patients', 1);

        // La liaison passe par la table d'identifiants externes du module :
        // c'est elle, et non un rapprochement sur le nom, qui garantit qu'on
        // retrouvera le meme dossier la fois suivante.
        $this->assertDatabaseHas('dme_patient_identifiers', [
            'system' => 'keneya_workflow',
            'value' => $patient->patient_code,
        ]);
    }

    public function test_un_second_acces_reprend_le_meme_dossier(): void
    {
        [$medecin, $patient] = $this->medecinEtSonPatient(avecDme: true);

        $this->actingAs($medecin)->get(route('dossier-medical.ouvrir', $patient));
        $premier = DmePatient::firstOrFail()->getKey();

        $this->actingAs($medecin)->get(route('dossier-medical.ouvrir', $patient));

        $this->assertDatabaseCount('dme_patients', 1);
        $this->assertSame($premier, DmePatient::firstOrFail()->getKey());
    }

    // ------------------------------------------------------ 5. SMS partages

    public function test_un_sms_declenche_depuis_le_module_part_par_la_file_de_workflow(): void
    {
        [$medecin, $patient] = $this->medecinEtSonPatient(avecDme: true);
        $this->actingAs($medecin)->get(route('dossier-medical.ouvrir', $patient));

        $dossier = DmePatient::firstOrFail();

        // La passerelle est simulee : ce qu'on verifie ici n'est pas qu'un SMS
        // parte pour de bon, mais qu'il emprunte le chemin de WorkFlow.
        $this->mock(SmsGateway::class)->shouldReceive('deliver')->andReturn(SmsSendResult::sent());

        // C'est par ce contrat, et uniquement par lui, que le module emet.
        app(SmsDispatcherContract::class)->dispatch(
            '76000000',
            'Votre rendez-vous est confirme.',
            (string) SmsContext::for(patient: $dossier),
        );

        // La trace atterrit dans `sms_messages` de WorkFlow — la table que
        // surveille la section « SMS » de /admin — et nulle part ailleurs.
        // Le statut « envoye » n'est pas decoratif : seul SendSmsJob::handle()
        // l'ecrit, ce qui prouve que le message a bien traverse la file de
        // l'hote et non un envoi direct depuis le module.
        $this->assertDatabaseHas('sms_messages', [
            'to' => '76000000',
            'body' => 'Votre rendez-vous est confirme.',
            'status' => SmsMessage::STATUS_SENT,
        ]);

        $this->assertDatabaseCount('dme_sms_messages', 0);
    }

    public function test_le_contrat_sms_du_module_est_bien_celui_de_l_hote(): void
    {
        $this->assertInstanceOf(
            WorkflowSmsDispatcher::class,
            app(SmsDispatcherContract::class),
        );
    }

    // --------------------------------------------- 6. Journal d'audit unique

    public function test_une_action_du_module_apparait_dans_le_journal_de_l_admin(): void
    {
        [$medecin, $patient] = $this->medecinEtSonPatient(avecDme: true);
        $this->actingAs($medecin)->get(route('dossier-medical.ouvrir', $patient));

        $dossier = DmePatient::firstOrFail();

        $this->actingAs($medecin);
        $consultation = Consultation::create([
            'patient_id' => $dossier->getKey(),
            'doctor_id' => $medecin->getKey(),
            'started_at' => now(),
            'reason' => 'Fievre persistante.',
        ]);

        // Un seul journal : la ligne du module est dans la meme table, sous
        // son nom de journal, et l'ecran de /admin la restitue.
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'medical',
            'subject_type' => Consultation::class,
            'subject_id' => $consultation->getKey(),
            'causer_id' => $medecin->getKey(),
        ]);

        Livewire::actingAs($this->makeAdmin())
            ->test(ActivityLogViewer::class)
            ->assertSee($medecin->name);
    }
}
