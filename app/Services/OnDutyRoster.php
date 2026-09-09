<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceKind;
use App\Models\StaffMember;
use App\Models\User;
use App\Support\Roles;
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
 * rattachement de la personne : un medecin de Medecine Generale planifie « 08h
 * a 14h, aucun service » est de garde a Medecine Generale, ce qui est la seule
 * lecture raisonnable.
 *
 * Une receptionniste ou un caissier n'ont pas de service de rattachement : leur
 * creneau doit nommer le service (Accueil, Caisse Ticket...). C'est precisement
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

    /**
     * Qui prevenir pour ce service.
     *
     * Le personnel de garde d'abord : c'est la bonne reponse quand le planning
     * est tenu. Mais un planning vide ne doit pas valoir silence : sans aucun
     * creneau saisi, ce qui est l'etat de toute installation neuve et de bien
     * des journees ensuite, personne n'etait prevenu de rien. La cloche restait
     * muette, le son ne partait jamais, et rien n'expliquait pourquoi.
     *
     * A defaut de garde, on previent donc le personnel RATTACHE au service : un
     * medecin d'Echographie est la bonne personne a qui dire qu'un patient
     * attend en echographie, planning ou pas. Le repli s'arrete la : jamais
     * « tout le monde », qui transformerait la cloche en bruit de fond.
     *
     * Ce repli est deliberement separe de `for()`, qui reste la reponse exacte
     * a « qui est de garde ». Elargir celle-la elargirait aussi des acces :
     * c'est elle que `User::isOnDuty()` consulte.
     *
     * @return Collection<int, User>
     */
    public function aPrevenir(?int $serviceId, ?string $capability = null, ?Carbon $moment = null): Collection
    {
        $deGarde = $this->for($serviceId, $capability, $moment);

        if ($deGarde->isNotEmpty() || ! $serviceId) {
            return $deGarde;
        }

        return $this->rattaches($serviceId, $capability)
            ->whenEmpty(fn () => $this->parRoleDuType($serviceId, $capability));
    }

    /**
     * Dernier recours : le role qu'implique le TYPE du service.
     *
     * Une receptionniste et un caissier n'ont aucun rattachement a un service :
     * leur lien passe uniquement par le planning, et le repli precedent ne
     * donne donc rien pour l'Accueil ou une caisse. Or c'est precisement a
     * l'Accueil qu'un patient entre dans une file : y rester muet etait le plus
     * couteux des silences.
     *
     * Le type du service porte deja l'information, par son slug : un service de
     * type « reception » est tenu par des receptionnistes, un service de type
     * « caisse » par des caissiers. Rien a migrer, rien a saisir.
     *
     * Les types soignants ne sont pas concernes : leur personnel est rattache
     * par sa fiche, le repli precedent l'a deja trouve.
     *
     * @return Collection<int, User>
     */
    private function parRoleDuType(int $serviceId, ?string $capability): Collection
    {
        $slug = Service::with('serviceKind')->find($serviceId)?->serviceKind?->slug;

        $role = match ($slug) {
            ServiceKind::SLUG_RECEPTION => Roles::RECEPTIONIST,
            ServiceKind::SLUG_CAISSE => Roles::CASHIER,
            default => null,
        };

        if (! $role) {
            return new Collection;
        }

        // `User::role()` de Spatie leve une exception si le role n'existe pas
        // encore en base. Une notification ne doit jamais faire echouer l'acte
        // metier qui l'a declenchee : on interroge la relation directement, ce
        // qui rend simplement une liste vide.
        $users = User::whereHas('roles', fn ($q) => $q->where('name', $role))->get();

        return $capability === null
            ? $users
            : $users->filter(fn (User $user) => $user->hasCapability($capability))->values();
    }

    /**
     * Le personnel rattache a un service par sa fiche, et non par un creneau.
     *
     * Une receptionniste et un caissier n'ont pas de rattachement : leur lien
     * au service passe uniquement par le planning. Pour eux, le repli ne donne
     * rien, et c'est exact, rien dans les donnees ne dit a quel guichet ils
     * appartiennent. La section Plannings signale ces services-la.
     *
     * @return Collection<int, User>
     */
    private function rattaches(int $serviceId, ?string $capability): Collection
    {
        $ids = Doctor::where('service_id', $serviceId)->pluck('user_id')
            ->merge(StaffMember::where('service_id', $serviceId)->pluck('user_id'))
            ->filter()
            ->unique();

        if ($ids->isEmpty()) {
            return new Collection;
        }

        $users = User::whereIn('id', $ids)->get();

        return $capability === null
            ? $users
            : $users->filter(fn (User $user) => $user->hasCapability($capability))->values();
    }

    /**
     * Les services sur lesquels personne n'est de garde en ce moment.
     *
     * Sert a rendre visible ce qui etait silencieux : un service sans garde ne
     * recoit ses notifications que par le repli ci-dessus, et pas du tout si
     * personne ne lui est rattache.
     *
     * @return Collection<int, Service>
     */
    public function servicesSansGarde(?Carbon $moment = null): Collection
    {
        return Service::orderBy('name')->get()
            ->filter(fn ($service) => $this->for($service->getKey(), null, $moment)->isEmpty())
            ->values();
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
     * en une ligne, `start_time <= T <= end_time`, et elle est fausse des que
     * la fin precede le debut. Un « 22h - 06h » ne rendait alors de garde a
     * *aucune* heure : ni a 22h30, ni a 2h du matin. Dans un hopital qui tourne
     * la nuit, cela suffit a expliquer qu'un declencheur parte le jour et se
     * taise le soir, sans que rien ne l'annonce.
     *
     * Un creneau du 10 mars « 22h - 06h » couvre donc le 10 a partir de 22h,
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
     * Les comptes rattaches a ce service par une colonne : medecins et
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
