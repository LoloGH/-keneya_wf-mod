<?php

declare(strict_types=1);

namespace Keneya\Dme\Console\Commands;

use Keneya\Dme\Models\AuditLog;
use Keneya\Dme\Models\UserDutyPeriod;
use Illuminate\Console\Command;

/**
 * Bascule is_on_duty selon les gardes planifiées à l'avance par
 * l'administrateur, en plus du bouton de prise de garde manuelle.
 *
 * activated_at / deactivated_at rendent la bascule idempotente : une
 * garde n'est prise qu'une fois, et si l'intéressé la quitte à la main
 * en cours de période, cette tâche ne la réactive pas.
 *
 * À planifier toutes les cinq minutes (voir routes/console.php).
 */
class SyncDutyPeriods extends Command
{
    protected $signature = 'keneya:duty-periods:sync';

    protected $description = 'Prend et termine automatiquement les gardes planifiées à l\'avance';

    public function handle(): int
    {
        $started = 0;
        $ended = 0;

        UserDutyPeriod::query()
            ->whereNull('activated_at')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->with('user')
            ->each(function (UserDutyPeriod $period) use (&$started): void {
                $period->user->forceFill([
                    'is_on_duty' => true,
                    'on_duty_since' => $period->starts_at,
                ])->save();

                $period->forceFill(['activated_at' => now()])->save();

                AuditLog::record(
                    action: 'duty_started',
                    subject: $period->user,
                    description: 'A pris la garde (planifiée)',
                );

                $started++;
            });

        UserDutyPeriod::query()
            ->whereNotNull('activated_at')
            ->whereNull('deactivated_at')
            ->where('ends_at', '<=', now())
            ->with('user')
            ->each(function (UserDutyPeriod $period) use (&$ended): void {
                $stillActive = UserDutyPeriod::query()
                    ->where('user_id', $period->user_id)
                    ->where('id', '!=', $period->id)
                    ->currentlyActive()
                    ->exists();

                if (! $stillActive) {
                    $period->user->forceFill([
                        'is_on_duty' => false,
                        'on_duty_since' => null,
                    ])->save();

                    AuditLog::record(
                        action: 'duty_ended',
                        subject: $period->user,
                        description: 'A quitté la garde (fin de période planifiée)',
                    );
                }

                $period->forceFill(['deactivated_at' => now()])->save();

                $ended++;
            });

        $this->info("{$started} garde(s) démarrée(s), {$ended} garde(s) terminée(s).");

        return self::SUCCESS;
    }
}
