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
            ->where(fn (Builder $query) => $this->couvrant($query, $moment))
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
     * Les creneaux qui contiennent cet instant, creneaux de nuit compris.
     *
     * **Le creneau qui franchit minuit (v3.2.8, point 2).** La condition tenait
     * en une ligne — `start_time <= T <= end_time` — et elle est fausse des que
     * la fin precede le debut. Un « 22h – 06h » ne rendait alors de garde a
     * *aucune* heure : ni a 22h30, ni a 2h du matin. Dans un hopital qui tourne
     * la nuit, cela suffit a expliquer qu'un declencheur parte le jour et se
     * taise le soir, sans que rien ne l'annonce.
     *
     * Un creneau du 10 mars « 22h – 06h » couvre donc le 10 a partir de 22h,
     * *et* le 11 jusqu'a 6h : c'est pourquoi la veille est interrogee elle
     * aussi. Les deux bornes restent inclusives, comme avant.
     */
    private function couvrant(Builder $query, Carbon $moment): void
    {
        $heure = $moment->format('H:i:s');
        $jour = $moment->toDateString();
        $veille = $moment->copy()->subDay()->toDateString();

        // Creneau ordinaire du jour : debut <= maintenant <= fin.
        $query->where(fn (Builder $ordinaire) => $ordinaire
            ->whereDate('date', $jour)
            ->whereColumn('start_time', '<=', 'end_time')
            ->whereTime('start_time', '<=', $heure)
            ->whereTime('end_time', '>=', $heure))

            // Creneau de nuit commence aujourd'hui : sa soiree.
            ->orWhere(fn (Builder $soiree) => $soiree
                ->whereDate('date', $jour)
                ->whereColumn('start_time', '>', 'end_time')
                ->whereTime('start_time', '<=', $heure))

            // Creneau de nuit commence hier : son petit matin.
            ->orWhere(fn (Builder $matin) => $matin
                ->whereDate('date', $veille)
                ->whereColumn('start_time', '>', 'end_time')
                ->whereTime('end_time', '>=', $heure));
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
