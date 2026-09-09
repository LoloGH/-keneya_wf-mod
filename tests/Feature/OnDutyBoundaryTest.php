<?php

namespace Tests\Feature;

use App\Actions\CheckInAppointment;
use App\Actions\CompleteReferral;
use App\Actions\ConfirmCaissePayment;
use App\Actions\ScheduleAppointment;
use App\Actions\SendReferral;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceKind;
use App\Models\StaffNotification;
use App\Models\User;
use App\Models\Visit;
use App\Services\OnDutyRoster;
use App\Services\SmsGateway;
use App\Services\SmsSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Le filtre « de garde », aux heures ou il se trompait (v3.2.8, point 2).
 *
 * Les notifications ne partaient pas systematiquement. Trois pistes ont ete
 * examinees ; ces tests fixent ce qu'elles ont donne, pour qu'aucune ne se
 * reinstalle sans qu'on le voie :
 *
 *  - **Fuseau horaire** : ecarte, et le test ci-dessous le montre plutot que
 *    de l'affirmer. `Africa/Bamako` vaut UTC+00:00 toute l'annee, le Mali n'a
 *    pas d'heure d'ete, donc un decalage base/affichage y est arithmetiquement
 *    impossible. Les bornes n'en sont pas moins verrouillees ici : c'est la
 *    zone qui ne peut pas deriver, pas le code qui la lit.
 *  - **Contournement des evenements de modele** : ecarte. Les six chemins qui
 *    font entrer une visite dans une file passent tous par `$visit->update()`
 *    sur une instance, jamais par une requete de masse ; les tests par
 *    declencheur ci-dessous le verifient chemin par chemin.
 *  - **Creneau franchissant minuit** : c'est la que se trouvait le defaut.
 */
class OnDutyBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->mock(SmsGateway::class)->shouldReceive('deliver')->andReturn(SmsSendResult::sent());
    }

    /**
     * Les quatre secondes qui decident : juste avant la prise de service, a la
     * prise, a la releve, juste apres. Une regression sur les bornes se verrait
     * ici avant de se voir a l'hopital.
     */
    public function test_les_bornes_de_la_plage_sont_inclusives_a_la_seconde_pres(): void
    {
        [$user, $service] = $this->creneau('08:00:00', '14:00:00');

        foreach ([
            '07:59:59' => false,
            '08:00:00' => true,
            '08:00:01' => true,
            '13:59:59' => true,
            '14:00:00' => true,
            '14:00:01' => false,
        ] as $heure => $attendu) {
            $this->assertSame(
                $attendu,
                $this->deGardeA($user, $service, $heure),
                sprintf('A %s, la garde devrait valoir %s.', $heure, $attendu ? 'vrai' : 'faux'),
            );
        }
    }

    /**
     * Le defaut trouve : un « 22h - 06h » ne rendait de garde a aucune heure,
     * ni le soir ni au petit matin. Dans un hopital qui tourne la nuit, cela
     * suffisait a faire taire un declencheur passe une certaine heure.
     */
    public function test_un_creneau_de_nuit_couvre_la_soiree_et_le_petit_matin(): void
    {
        [$user, $service] = $this->creneau('22:00:00', '06:00:00');

        // Le soir meme, apres la prise de service.
        $this->assertTrue($this->deGardeA($user, $service, '22:00:00'));
        $this->assertTrue($this->deGardeA($user, $service, '23:59:59'));

        // Le lendemain matin, sous le creneau de la veille.
        $this->assertTrue($this->deGardeA($user, $service, '00:00:00', jour: '2026-03-11'));
        $this->assertTrue($this->deGardeA($user, $service, '05:59:59', jour: '2026-03-11'));
        $this->assertTrue($this->deGardeA($user, $service, '06:00:00', jour: '2026-03-11'));

        // Et nulle part ailleurs.
        $this->assertFalse($this->deGardeA($user, $service, '21:59:59'));
        $this->assertFalse($this->deGardeA($user, $service, '06:00:01', jour: '2026-03-11'));
        $this->assertFalse($this->deGardeA($user, $service, '12:00:00', jour: '2026-03-11'));
    }

    /** Un creneau de jour ne doit surtout pas se mettre a deborder. */
    public function test_un_creneau_de_jour_ne_deborde_pas_sur_le_lendemain(): void
    {
        [$user, $service] = $this->creneau('08:00:00', '14:00:00');

        $this->assertFalse($this->deGardeA($user, $service, '10:00:00', jour: '2026-03-11'));
        $this->assertFalse($this->deGardeA($user, $service, '02:00:00', jour: '2026-03-11'));
    }

    /**
     * La zone de l'application ne doit pas deriver de celle du serveur : c'est
     * l'egalite qui rend la comparaison de garde fiable, et sa rupture qui
     * relancerait la piste ecartee ci-dessus.
     */
    public function test_la_zone_de_l_application_est_celle_du_mali_sans_decalage(): void
    {
        $this->assertSame('Africa/Bamako', config('app.timezone'));
        $this->assertSame('+00:00', now()->format('P'));
        $this->assertSame(gmdate('Y-m-d H'), now()->format('Y-m-d H'));
    }

    // ------------------------------------------- Les cinq declencheurs

    /**
     * Chemin 1 : sortie de caisse. La visite change de service par
     * `$visit->update()`, donc l'evenement `updated` se declenche.
     */
    public function test_la_sortie_de_caisse_previent_le_service_de_destination(): void
    {
        [$ticket] = $this->makeCaisses();
        $medecine = Service::factory()->create(['name' => 'Medecine Generale']);

        $medecin = $this->makeDoctor($medecine);
        $this->deGardeToutLeJour($medecin->user, $medecine);

        $visit = $this->makeVisit($ticket, [
            'pending_next_service_id' => $medecine->getKey(),
            'status' => Visit::STATUS_WAITING,
        ]);

        app(ConfirmCaissePayment::class)->execute($visit, $this->makeCashier(), 1000);

        $this->assertSame(1, $this->notificationsDeFile($medecin->user_id));
    }

    /** Chemin 2 : retour d'un renvoi complete vers le prescripteur. */
    public function test_le_retour_d_un_renvoi_previent_le_prescripteur_de_garde(): void
    {
        $medecine = Service::factory()->create(['name' => 'Medecine Generale']);
        $laboratoire = Service::factory()
            ->ofKind($this->serviceKind(ServiceKind::SLUG_PLATEAU_TECHNIQUE))
            ->create(['name' => 'Laboratoire']);

        $prescripteur = $this->makeDoctor($medecine);
        $biologiste = $this->makeDoctor($laboratoire);

        $visit = $this->makeVisit($medecine, ['status' => Visit::STATUS_CALLED]);
        $referral = app(SendReferral::class)->execute($visit, $prescripteur, $laboratoire, 'Numeration.');

        // Mis de garde seulement maintenant : on ne compte que le retour.
        $this->deGardeToutLeJour($prescripteur->user, $medecine);

        app(CompleteReferral::class)->execute($referral, $biologiste, 'Resultat normal.');

        $this->assertSame(1, $this->notificationsDeFile($prescripteur->user_id));
    }

    /** Chemin 3 : arrivee sur rendez-vous. */
    public function test_une_arrivee_sur_rendez_vous_previent_le_service(): void
    {
        $medecine = Service::factory()->create(['name' => 'Medecine Generale']);
        $medecin = $this->makeDoctor($medecine);

        $consultation = $this->makeVisit($medecine, ['status' => Visit::STATUS_CALLED]);

        $appointment = app(ScheduleAppointment::class)
            ->execute($consultation, $medecin, now()->addDay());

        // De garde seulement maintenant : seule l'arrivee sur rendez-vous compte.
        $this->deGardeToutLeJour($medecin->user, $medecine);

        app(CheckInAppointment::class)->execute($appointment);

        $this->assertSame(1, $this->notificationsDeFile($medecin->user_id));
    }

    /**
     * Un creneau de nuit ne sert a rien s'il ne declenche pas les
     * notifications : le chemin complet est verifie a 2 h du matin.
     */
    public function test_un_patient_arrivant_la_nuit_previent_le_personnel_de_nuit(): void
    {
        $medecine = Service::factory()->create(['name' => 'Urgences']);
        $medecin = $this->makeDoctor($medecine);

        Schedule::create([
            'user_id' => $medecin->user_id,
            'service_id' => $medecine->getKey(),
            'date' => '2026-03-10',
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
        ]);

        // 2 h du matin le 11 : couvert par le creneau de la veille.
        $this->travelTo(Carbon::parse('2026-03-11 02:00:00', config('app.timezone')));

        $this->makeVisit($medecine, ['status' => Visit::STATUS_WAITING]);

        $this->assertSame(1, $this->notificationsDeFile($medecin->user_id));
    }

    // ------------------------------------------- Utilitaires

    /** @return array{0: User, 1: Service} */
    private function creneau(string $debut, string $fin): array
    {
        $service = Service::factory()->create();
        $user = User::factory()->create();

        Schedule::create([
            'user_id' => $user->getKey(),
            'service_id' => $service->getKey(),
            'date' => '2026-03-10',
            'start_time' => $debut,
            'end_time' => $fin,
        ]);

        return [$user, $service];
    }

    private function deGardeA(User $user, Service $service, string $heure, string $jour = '2026-03-10'): bool
    {
        return app(OnDutyRoster::class)->isOnDuty(
            $user,
            $service->getKey(),
            Carbon::parse($jour.' '.$heure, config('app.timezone')),
        );
    }

    private function deGardeToutLeJour(User $user, Service $service): void
    {
        Schedule::create([
            'user_id' => $user->getKey(),
            'service_id' => $service->getKey(),
            'date' => today()->toDateString(),
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
        ]);
    }

    private function notificationsDeFile(int $userId): int
    {
        return StaffNotification::where('user_id', $userId)
            ->where('type', StaffNotification::TYPE_NEW_QUEUE_ENTRY)
            ->count();
    }
}
