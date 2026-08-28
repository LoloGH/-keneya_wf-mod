<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rythme de rafraichissement des ecrans de travail (v3.2.5).
 *
 * Trois ecrans portaient leur propre intervalle en dur — 10 s, 15 s, 30 s. Un
 * soin prescrit mettait donc jusqu'a trente secondes a apparaitre chez
 * l'infirmier, et la notification arrivait bien avant la tache annoncee.
 */
class PollIntervalTest extends TestCase
{
    use RefreshDatabase;

    /** Les vues qui montrent du travail entrant et doivent suivre le rythme commun. */
    private const VUES = [
        'livewire/shared/notification-bell',
        'livewire/reception/today-appointments',
        'livewire/reception/today-visits',
        'livewire/caisse/caisse-queue',
        'livewire/staff/staff-incoming-referrals',
        'livewire/staff/staff-queue',
        'livewire/staff/staff-care-tasks',
        'livewire/service/incoming-referrals',
        'livewire/service/outgoing-referrals',
        'livewire/service/service-queue',
    ];

    public function test_l_intervalle_livre_est_de_cinq_secondes(): void
    {
        // On lit ce qui est versionne, pas la valeur resolue : un .env local
        // ferait passer ou echouer ce test selon la machine, alors que ce qui
        // compte est ce que recoit une installation neuve.
        $this->assertStringContainsString(
            "env('KENEYA_POLL_INTERVAL', '5s')",
            file_get_contents(config_path('keneya.php')),
        );

        $this->assertStringContainsString(
            'KENEYA_POLL_INTERVAL=5s',
            file_get_contents(base_path('.env.example')),
        );
    }

    public function test_aucun_ecran_de_travail_ne_porte_son_propre_intervalle(): void
    {
        foreach (self::VUES as $vue) {
            $source = file_get_contents(resource_path('views/'.$vue.'.blade.php'));

            $this->assertStringContainsString(
                "wire:poll.{{ config('keneya.poll_interval') }}",
                $source,
                $vue.' doit suivre l\'intervalle commun.',
            );

            // Un intervalle en dur rendrait la configuration mensongere.
            $this->assertDoesNotMatchRegularExpression(
                '/wire:poll\.\d+s/',
                $source,
                $vue.' porte encore un intervalle code en dur.',
            );
        }
    }
}
