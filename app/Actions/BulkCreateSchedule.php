<?php

namespace App\Actions;

use App\Models\Schedule;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creation groupee de creneaux (v3.2, point 2).
 *
 * Remplir un planning ligne par ligne pour un mois entier est intenable :
 * l'admin decrit une plage de dates et les jours de la semaine concernes, et
 * l'on genere une ligne par date correspondante.
 *
 * Le formulaire jour par jour subsiste pour les ajustements ponctuels.
 */
class BulkCreateSchedule
{
    /**
     * @param  array<int, int>  $weekdays  1 = lundi … 7 = dimanche (ISO-8601)
     * @return int nombre de creneaux crees
     */
    public function execute(
        User $user,
        Carbon $from,
        Carbon $to,
        array $weekdays,
        string $startTime,
        string $endTime,
        ?int $serviceId = null,
    ): int {
        if ($to->lt($from)) {
            throw new InvalidArgumentException('La date de fin doit suivre la date de debut.');
        }

        if ($weekdays === []) {
            throw new InvalidArgumentException('Choisissez au moins un jour de la semaine.');
        }

        if ($startTime >= $endTime) {
            throw new InvalidArgumentException("L'heure de fin doit suivre l'heure de debut.");
        }

        $jours = array_map('intval', $weekdays);
        $crees = 0;

        // Normalisation en HH:MM:SS avant ecriture comme avant comparaison :
        // MySQL range une colonne `time` sous cette forme, SQLite conserve la
        // chaine telle qu'elle a ete inseree. Sans cela, la detection de
        // doublon ci-dessous ne retomberait jamais sur ses pieds.
        $debut = $this->normalizeTime($startTime);
        $fin = $this->normalizeTime($endTime);

        DB::transaction(function () use ($user, $from, $to, $jours, $debut, $fin, $serviceId, &$crees): void {
            for ($date = $from->copy()->startOfDay(); $date->lte($to); $date->addDay()) {
                if (! in_array($date->dayOfWeekIso, $jours, true)) {
                    continue;
                }

                // Un creneau identique existant n'est pas duplique : l'admin
                // peut relancer une generation sans salir le planning.
                $existe = Schedule::where('user_id', $user->getKey())
                    ->whereDate('date', $date)
                    ->where('start_time', $debut)
                    ->where('end_time', $fin)
                    ->exists();

                if ($existe) {
                    continue;
                }

                Schedule::create([
                    'user_id' => $user->getKey(),
                    'date' => $date->toDateString(),
                    'start_time' => $debut,
                    'end_time' => $fin,
                    'service_id' => $serviceId,
                ]);

                $crees++;
            }
        });

        Audit::log(
            Audit::EVENT_SCHEDULE_BULK,
            sprintf(
                '%d creneau(x) genere(s) pour %s du %s au %s (%s–%s).',
                $crees,
                $user->name,
                $from->format('d/m/Y'),
                $to->format('d/m/Y'),
                $startTime,
                $endTime,
            ),
            $user,
        );

        return $crees;
    }

    /** « 08:00 » comme « 08:00:00 » donnent la meme chaine comparable. */
    private function normalizeTime(string $time): string
    {
        return substr($time, 0, 5).':00';
    }
}
