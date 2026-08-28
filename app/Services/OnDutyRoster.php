<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\Schedule;
use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Qui est de garde, sur quel service, maintenant (v3.2.3, point 2).
 *
 * Unique regle de garde de l'application : « moi, maintenant ? » comme « eux,
 * maintenant ? » passent par ici. Deux regles auraient immanquablement diverge.
 *
 * `schedules` est le bon support et le seul : il porte a la fois la personne et
 * le service, quelle que soit sa table de rattachement.
 *
 * **Le creneau sans service (v3.2.5).** Le formulaire presente le service comme
 * facultatif, et il l'est pour lire son propre planning. Mais un creneau muet
 * ne rendait de garde pour *aucun* service : ni notification, ni soin visible,
 * sans que rien ne le dise. Un tel creneau vaut desormais pour les services de
 * rattachement de la personne — un medecin de Medecine Generale planifie « 08h
 * a 14h, aucun service » est de garde a Medecine Generale, ce qui est la seule
 * lecture raisonnable.
 *
 * Une receptionniste ou un caissier n'ont pas de service de rattachement : leur
 * creneau doit nommer le service (Accueil, Caisse Ticket…). C'est precisement
 * ce pour quoi l'accueil est devenu un service.
 */
class OnDutyRoster
{
    /**
     * Le personnel de garde sur ce service a cet instant.
     *
     * `$capability` restreint aux comptes dont le type porte la capacite : on
     * ne previent pas d'une prescription de soins quelqu'un qui n'a pas la
     * section pour les voir.
     *
     * @return Collection<int, User>
     */
    public function for(?int $serviceId, ?string $capability = null, ?Carbon $moment = null): Collection
    {
        if (! $serviceId) {
            return new Collection;
        }

        $ids = $this->coveringSchedules($serviceId, $moment)->pluck('user_id')->unique();

        if ($ids->isEmpty()) {
            return new Collection;
        }

        $users = User::whereIn('id', $ids)->get();

        return $capability === null
            ? $users
            : $users->filter(fn (User $user) => $user->hasCapability($capability))->values();
    }

    /** Cette personne est-elle de garde sur ce service, maintenant ? */
    public function isOnDuty(User $user, int $serviceId, ?Carbon $moment = null): bool
    {
        return $this->coveringSchedules($serviceId, $moment)
            ->where('user_id', $user->getKey())
            ->exists();
    }

    /**
     * Les creneaux qui couvrent l'instant donne pour ce service, service muet
     * compris.
     *
     * @return Builder<Schedule>
     */
    private function coveringSchedules(int $serviceId, ?Carbon $moment = null): Builder
    {
        $moment ??= now();
        $attaches = $this->attachedUserIds($serviceId);

        return Schedule::query()
            ->whereDate('date', $moment->toDateString())
            ->whereTime('start_time', '<=', $moment->format('H:i:s'))
            ->whereTime('end_time', '>=', $moment->format('H:i:s'))
            ->where(function (Builder $query) use ($serviceId, $attaches) {
                $query->where('service_id', $serviceId);

                if ($attaches !== []) {
                    $query->orWhere(fn (Builder $muet) => $muet
                        ->whereNull('service_id')
                        ->whereIn('user_id', $attaches));
                }
            });
    }

    /**
     * Les comptes rattaches a ce service par une colonne — medecins et
     * personnel generique. Une receptionniste ou un caissier n'en font jamais
     * partie : leurs tables ne portent pas de service.
     *
     * @return array<int, int>
     */
    private function attachedUserIds(int $serviceId): array
    {
        return array_values(array_unique(array_merge(
            Doctor::where('service_id', $serviceId)->pluck('user_id')->all(),
            StaffMember::where('service_id', $serviceId)->pluck('user_id')->all(),
        )));
    }
}
