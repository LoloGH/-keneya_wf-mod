<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceKind;
use App\Models\StaffNotification;
use App\Services\OnDutyRoster;
use App\Services\StaffNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Qui recoit les notifications, et pourquoi elles n'arrivaient nulle part.
 *
 * Le ciblage passait par « qui est de garde », c'est-a-dire par le planning.
 * Sur une installation sans creneau saisi — l'etat de toute installation neuve,
 * et de bien des journees ensuite — cela donnait zero destinataire pour tous
 * les services : la cloche restait vide sur chaque interface, le son ne partait
 * jamais, et rien n'expliquait pourquoi.
 *
 * Le ciblage descend desormais trois marches, et s'arrete a la premiere qui
 * donne quelqu'un. Ce fichier verifie les trois, et surtout qu'elles se
 * franchissent dans le bon ordre : un planning tenu doit rester la seule regle
 * qui compte.
 */
class NotificationTargetingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    // --------------------------------------------- 1. Le planning d'abord

    public function test_le_personnel_de_garde_est_le_seul_prevenu_quand_le_planning_est_tenu(): void
    {
        $service = Service::factory()->create();

        $deGarde = $this->makeDoctor($service);
        $absent = $this->makeDoctor($service);

        Schedule::factory()->create([
            'user_id' => $deGarde->user_id,
            'service_id' => $service->getKey(),
            'date' => today(),
            'start_time' => now()->subHour()->format('H:i'),
            'end_time' => now()->addHours(3)->format('H:i'),
        ]);

        app(StaffNotifier::class)->queueEntry($service->getKey(), 'Aminata Traore');

        $this->assertSame(1, StaffNotification::where('user_id', $deGarde->user_id)->count());
        // Le rattachement ne doit PAS rattraper quelqu'un qui n'est pas de
        // garde : sinon le planning ne servirait plus a rien.
        $this->assertSame(0, StaffNotification::where('user_id', $absent->user_id)->count());
    }

    // ------------------------------------- 2. A defaut, le rattachement

    public function test_sans_aucun_creneau_le_personnel_rattache_au_service_est_prevenu(): void
    {
        $service = Service::factory()->create();
        $medecin = $this->makeDoctor($service);

        $this->assertSame(0, Schedule::count(), 'Le test doit partir d\'un planning vide.');

        app(StaffNotifier::class)->queueEntry($service->getKey(), 'Aminata Traore');

        $this->assertSame(1, StaffNotification::where('user_id', $medecin->user_id)->count());
    }

    public function test_le_repli_ne_deborde_pas_sur_un_autre_service(): void
    {
        $echographie = Service::factory()->create(['name' => 'Echographie']);
        $laboratoire = Service::factory()->create(['name' => 'Laboratoire']);

        $ailleurs = $this->makeDoctor($laboratoire);

        app(StaffNotifier::class)->queueEntry($echographie->getKey(), 'Aminata Traore');

        $this->assertSame(0, StaffNotification::where('user_id', $ailleurs->user_id)->count());
    }

    // ----------------------- 3. A defaut encore, le role qu'implique le type

    public function test_l_accueil_previent_les_receptionnistes_faute_de_rattachement(): void
    {
        // Une receptionniste n'a aucun service de rattachement : son lien passe
        // uniquement par le planning. Sans creneau, l'Accueil restait donc
        // muet — alors que c'est precisement la qu'un patient entre dans une
        // file. Le type du service porte l'information, par son slug.
        $accueil = Service::factory()->ofKind(
            ServiceKind::where('slug', ServiceKind::SLUG_RECEPTION)->firstOrFail()
        )->create(['name' => 'Accueil']);

        $receptionniste = $this->makeReceptionist();

        app(StaffNotifier::class)->queueEntry($accueil->getKey(), 'Aminata Traore');

        $this->assertSame(1, StaffNotification::where('user_id', $receptionniste->getKey())->count());
    }

    public function test_une_caisse_previent_les_caissiers(): void
    {
        $caisse = Service::factory()->caisse()->create(['name' => 'Caisse Ticket']);
        $caissier = $this->makeCashier();

        app(StaffNotifier::class)->queueEntry($caisse->getKey(), 'Aminata Traore');

        $this->assertSame(1, StaffNotification::where('user_id', $caissier->getKey())->count());
    }

    public function test_un_service_soignant_ne_previent_jamais_tout_le_monde(): void
    {
        // Le troisieme repli ne vaut que pour les types « reception » et
        // « caisse ». Un service clinique sans personne rattachee reste muet
        // plutot que d'alerter l'hopital entier.
        $service = Service::factory()->create(['name' => 'Urgences']);

        $this->makeReceptionist();
        $this->makeCashier();
        $this->makeAdmin();

        app(StaffNotifier::class)->queueEntry($service->getKey(), 'Aminata Traore');

        $this->assertSame(0, StaffNotification::count());
    }

    // ------------------------------------------------- Rendre le trou visible

    public function test_les_services_sans_garde_sont_identifiables(): void
    {
        $avec = Service::factory()->create(['name' => 'Avec garde']);
        $sans = Service::factory()->create(['name' => 'Sans garde']);

        $medecin = $this->makeDoctor($avec);

        Schedule::factory()->create([
            'user_id' => $medecin->user_id,
            'service_id' => $avec->getKey(),
            'date' => today(),
            'start_time' => now()->subHour()->format('H:i'),
            'end_time' => now()->addHours(3)->format('H:i'),
        ]);

        $sansGarde = app(OnDutyRoster::class)->servicesSansGarde()->pluck('name');

        $this->assertTrue($sansGarde->contains($sans->name));
        $this->assertFalse($sansGarde->contains($avec->name));
    }

    // ------------------------------------------------------------ Le son

    public function test_un_fichier_de_son_est_bien_livre(): void
    {
        // Le son ne se declenchait jamais parce qu'aucune notification
        // n'arrivait, pas parce que le fichier manquait. On verifie tout de
        // meme qu'il est la : une cloche muette faute de fichier serait la
        // meme panne vue de l'utilisateur.
        $this->assertNotNull(
            notification_sound_url(),
            'Aucun fichier public/sounds/notification.{mp3,ogg,wav} : la cloche resterait muette.',
        );
    }
}
