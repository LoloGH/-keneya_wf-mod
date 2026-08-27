<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Qui est de garde, sur quel service, maintenant (v3.2.3, point 2).
 *
 * Unique regle de ciblage de l'application. Elle etait deja ecrite dans
 * `User::isOnDutyFor()` pour repondre « moi, maintenant ? » ; il manquait la
 * question inverse — « eux, maintenant ? » — que posent les notifications.
 * Les deux lisent la meme table et la meme fenetre horaire : une seconde
 * logique de ciblage aurait immanquablement divergé de la premiere.
 *
 * `schedules` est le bon support pour cela, et le seul : il porte a la fois la
 * personne et le service, quelle que soit sa table de rattachement. Un medecin,
 * une receptionniste, un caissier et un infirmier y entrent de la meme facon —
 * ce qui evite d'avoir a interroger quatre tables pour savoir qui prevenir.
 */
class OnDutyRoster
{
    /**
     * Le personnel de garde sur ce service a cet instant.
     *
     * `$capability` restreint aux comptes dont le type porte la capacite —
     * on ne previent pas d'une prescription de soins quelqu'un qui n'a pas la
     * section pour les voir.
     *
     * @return Collection<int, User>
     */
    public function for(?int $serviceId, ?string $capability = null, ?Carbon $moment = null): Collection
    {
        if (! $serviceId) {
            return new Collection;
        }

        $moment ??= now();

        $ids = Schedule::query()
            ->where('service_id', $serviceId)
            ->whereDate('date', $moment->toDateString())
            ->whereTime('start_time', '<=', $moment->format('H:i:s'))
            ->whereTime('end_time', '>=', $moment->format('H:i:s'))
            ->pluck('user_id')
            ->unique();

        if ($ids->isEmpty()) {
            return new Collection;
        }

        $users = User::whereIn('id', $ids)->get();

        return $capability === null
            ? $users
            : $users->filter(fn (User $user) => $user->hasCapability($capability))->values();
    }
}
