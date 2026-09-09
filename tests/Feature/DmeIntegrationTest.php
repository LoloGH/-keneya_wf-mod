<?php

namespace Tests\Feature;

use App\Livewire\Admin\ActivityLogViewer;
use App\Livewire\Admin\PatientDirectory;
use App\Livewire\Service\MyPatients;
use App\Models\Patient;
use App\Models\PatientHistory;
use App\Models\Service;
use App\Models\Setting;
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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Montage du module Dossier Medical Electronique dans WorkFlow (v3.3.0).
 *
 * Ces tests ne verifient pas le DME, il a sa propre suite, mais les six
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
     * Un medecin rattache a un service, dont le type de personnel porte, ou
     * non, la capacite d'ouvrir le dossier medical.
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

    /**
     * Le module porte le nom de l'etablissement, pas celui de son fichier de
     * configuration (v3.3.1).
     *
     * Il affichait « Centre Hospitalier Keneya », valeur d'exemple livree avec
     * le paquet, pendant que l'etablissement s'appelait autrement dans
     * /admin. Deux noms pour un seul hopital, sur des ecrans que le meme
     * praticien enchaine.
     */
    public function test_le_module_affiche_le_nom_de_l_etablissement_de_workflow(): void
    {
        Setting::put(Setting::HOSPITAL_NAME, 'Hopital Fousseyni Daou');

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('dme.dashboard'))
            ->assertOk()
            ->assertSee('Hopital Fousseyni Daou')
            ->assertDontSee(config('dme.facility.name'));
    }

    /**
     * La mention de demonstration n'a rien a faire sur un dossier reel.
     *
     * Elle reste par defaut dans le module, un jeu d'essai pris pour un vrai
     * dossier serait plus grave que l'inverse, et l'exploitant la leve. Ici,
     * l'assemblage la leve.
     */
    public function test_le_module_ne_dit_pas_que_les_donnees_sont_fictives(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('dme.dashboard'))
            ->assertOk()
            ->assertDontSee('Donnees de demonstration')
            ->assertDontSee('Données de démonstration', escape: false);
    }

    /**
     * On entre dans le module depuis WorkFlow : on doit pouvoir en ressortir
     * (v3.3.1).
     *
     * Sans ce lien, la seule issue etait le bouton « precedent » du
     * navigateur, ou la deconnexion, ce qui est pire. L'adresse depend du
     * role : le module ne peut pas la deviner, il la demande a l'hote.
     */
    public function test_le_module_offre_une_porte_de_retour_vers_workflow(): void
    {
        [$medecin, $patient] = $this->medecinEtSonPatient(avecDme: true);

        $this->actingAs($medecin)->get(route('dossier-medical.ouvrir', $patient));

        $this->actingAs($medecin)
            ->get(route('dme.dashboard'))
            ->assertOk()
            ->assertSee('Retour a '.config('keneya.name'))
            ->assertSee(route('service.home'), escape: false);
    }

    /**
     * Chacun repart vers son propre espace : l'administrateur n'a rien a
     * faire dans la file d'un service.
     */
    public function test_la_porte_de_retour_mene_a_l_espace_du_role(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('dme.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.home'), escape: false);
    }

    /**
     * L'administrateur entre toujours, et par sa propre interface.
     *
     * Il n'a aucun type de personnel : la case `can_access_dme` n'existe donc
     * nulle part pour lui dans /admin, et il serait le seul compte a ne jamais
     * pouvoir l'obtenir. Or c'est lui qui administre le module : parametres,
     * roles, comptes, journal d'audit. Il l'a donc de droit.
     */
    public function test_l_administrateur_entre_dans_le_module_sans_case_a_cocher(): void
    {
        $admin = $this->makeAdmin();

        $this->assertTrue($admin->canAccessDme());

        // `dme.home` redirige vers le tableau de bord : ce qui compte ici est
        // qu'il ne soit plus refuse a la porte.
        $this->actingAs($admin)->get(route('dme.home'))->assertRedirect(route('dme.dashboard'));

        $this->actingAs($admin)->get(route('dme.settings.index'))->assertSuccessful();
        $this->actingAs($admin)->get(route('dme.users.index'))->assertSuccessful();
        $this->actingAs($admin)->get(route('dme.audit.index'))->assertSuccessful();
    }

    /**
     * Retirer un dossier du DME est une prerogative de l'administrateur.
     *
     * Le module distingue deux gestes : archiver, qui range sans rien
     * detruire, et supprimer, qui detruit le dossier et tout son contenu
     * clinique. Le second est porte par une permission a part, `patients.purge`,
     * que la correspondance de roles ne donne qu'a l'administrateur de
     * WorkFlow. Un medecin n'a ni l'une ni l'autre.
     */
    public function test_seul_l_administrateur_peut_retirer_un_dossier_du_module(): void
    {
        [$medecin] = $this->medecinEtSonPatient(avecDme: true);
        $admin = $this->makeAdmin();

        $this->assertTrue($admin->can('patients.delete'), "L'admin doit pouvoir archiver.");
        $this->assertTrue($admin->can('patients.purge'), "L'admin doit pouvoir supprimer definitivement.");

        $this->assertFalse($medecin->can('patients.delete'));
        $this->assertFalse($medecin->can('patients.purge'));
    }

    public function test_l_administrateur_ouvre_le_dossier_medical_depuis_son_interface(): void
    {
        [, $patient] = $this->medecinEtSonPatient(avecDme: true);
        $admin = $this->makeAdmin();

        Livewire::actingAs($admin)
            ->test(PatientDirectory::class)
            ->call('openRecord', $patient->getKey())
            ->assertSee('Dossier medical complet');

        $this->actingAs($admin)
            ->get(route('dossier-medical.ouvrir', $patient))
            ->assertRedirect(route('dme.patients.show', DmePatient::firstOrFail()));
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

    /**
     * Les ecrans du module s'ouvrent tous sous un compte WorkFlow.
     *
     * Ce test n'existe pas pour verifier ce que chaque page affiche, le
     * module a sa propre suite pour cela, mais pour attraper la famille
     * d'erreurs que l'assemblage introduit, et qu'aucun des deux projets ne
     * voit seul : une table renommee referencee en dur quelque part, un role
     * du DME qui n'existe pas dans le vocabulaire de WorkFlow. Les deux se
     * traduisent par un 500, et les deux sont deja arrives.
     *
     * @return array<string, array{0: string}>
     */
    public static function ecransDuModule(): array
    {
        return [
            'accueil' => ['dme.home'],
            'tableau de bord' => ['dme.dashboard'],
            'patients' => ['dme.patients.index'],
            'nouveau patient' => ['dme.patients.create'],
            'consultations' => ['dme.consultations.index'],
            'rendez-vous' => ['dme.appointments.index'],
            'ordonnances' => ['dme.prescriptions.index'],
            'laboratoire' => ['dme.laboratory.index'],
            'imagerie' => ['dme.imaging.index'],
            'hospitalisations' => ['dme.hospitalizations.index'],
            'documents' => ['dme.documents.index'],
            'notifications' => ['dme.notifications.index'],
            'recherche' => ['dme.search'],
        ];
    }

    #[DataProvider('ecransDuModule')]
    public function test_les_ecrans_du_module_s_ouvrent_sous_un_compte_workflow(string $route): void
    {
        [$medecin] = $this->medecinEtSonPatient(avecDme: true);

        $reponse = $this->actingAs($medecin)->get(route($route));

        // Une page, une redirection interne ou un refus motive sont tous des
        // reponses ; une erreur serveur n'en est pas une. On ne juge donc pas
        // le code exact, les permissions du role peuvent legitimement fermer
        // un ecran, mais on refuse le 500.
        $this->assertLessThan(
            500,
            $reponse->getStatusCode(),
            "L'ecran {$route} a repondu ".$reponse->getStatusCode().'.',
        );

        // Et jamais un renvoi vers la page de connexion : la session de
        // WorkFlow doit suffire.
        $this->assertNotSame(route('login'), $reponse->headers->get('Location'));
    }

    public function test_le_dossier_d_un_patient_s_ouvre_et_se_modifie(): void
    {
        [$medecin, $patient] = $this->medecinEtSonPatient(avecDme: true);
        $this->actingAs($medecin)->get(route('dossier-medical.ouvrir', $patient));

        $dossier = DmePatient::firstOrFail();

        $this->actingAs($medecin)->get(route('dme.patients.show', $dossier))->assertSuccessful();
        $this->actingAs($medecin)->get(route('dme.patients.edit', $dossier))->assertSuccessful();
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

        // La trace atterrit dans `sms_messages` de WorkFlow, la table que
        // surveille la section « SMS » de /admin, et nulle part ailleurs.
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
