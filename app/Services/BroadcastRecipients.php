<?php

namespace App\Services;

use App\Models\BroadcastMessage;
use App\Models\Patient;
use App\Models\User;
use App\Support\BroadcastTarget;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Qui recevra une diffusion, et combien ils sont (v3.2.9, point 1).
 *
 * Deux exigences commandent la forme de cette classe :
 *
 * 1. **Le decompte annonce doit etre celui qui part.** L'administrateur voit
 *    un nombre avant de confirmer ; si l'apercu et l'envoi s'appuyaient sur
 *    deux requetes ecrites separement, elles divergeraient un jour. Les deux
 *    passent donc par `query()`, et par elle seule.
 *
 * 2. **Un envoi « en masse » ne doit rien charger en memoire.** Le decompte
 *    est un COUNT SQL, jamais un `get()->count()`, et l'iteration passe par
 *    `chunkById()`. Sur quelques dizaines de patients de demonstration la
 *    difference ne se voit pas ; sur les milliers de dossiers d'un hopital,
 *    c'est la difference entre un envoi qui part et un processus qui meurt.
 */
class BroadcastRecipients
{
    /** Taille des lots. Assez grand pour limiter les allers-retours, assez petit pour la memoire. */
    private const LOT = 500;

    public function count(BroadcastTarget $target): int
    {
        if ($target->isGroup()) {
            return $this->query($target)->count();
        }

        return $this->numeroUnique($target) === null ? 0 : 1;
    }

    /**
     * Appelle `$callback` avec le numero de chaque destinataire et rend le
     * nombre d'appels effectues.
     *
     * @param  Closure(string): void  $callback
     */
    public function each(BroadcastTarget $target, Closure $callback): int
    {
        if (! $target->isGroup()) {
            $numero = $this->numeroUnique($target);

            if ($numero === null) {
                return 0;
            }

            $callback($numero);

            return 1;
        }

        $envoyes = 0;

        // `chunkById` et non `chunk` : la pagination par decalage se decale
        // justement quand la table bouge pendant le parcours, et une
        // diffusion longue coexiste avec les enregistrements du jour.
        $this->query($target)->chunkById(self::LOT, function ($patients) use ($callback, &$envoyes): void {
            foreach ($patients as $patient) {
                $callback((string) $patient->mobile);
                $envoyes++;
            }
        });

        return $envoyes;
    }

    /**
     * La population visee, pour un groupe. Source unique du decompte comme de
     * l'envoi.
     *
     * @return Builder<Patient>
     */
    public function query(BroadcastTarget $target): Builder
    {
        return Patient::query()
            ->select(['id', 'mobile'])
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '')
            // Passe par ce service : on interroge `patient_history`, qui garde
            // la trace de chaque etape, et non `visits.service_id` qui ne
            // porte que le service courant — un patient renvoye ailleurs
            // depuis la radiologie y est bien passe.
            ->when(
                $target->type === BroadcastMessage::TARGET_PATIENT_GROUP && $target->serviceId,
                fn (Builder $query) => $query->whereHas(
                    'history',
                    fn (Builder $historique) => $historique->where('service_id', $target->serviceId),
                ),
            )
            ->when(
                $target->pathologyId,
                fn (Builder $query) => $query->whereHas(
                    'visits',
                    fn (Builder $visites) => $visites->where('pathology_id', $target->pathologyId),
                ),
            );
    }

    /**
     * Le numero d'un destinataire nommement designe — membre du personnel ou
     * patient. Nul si la personne n'a pas de telephone : on ne fabrique pas un
     * destinataire qui n'existe pas.
     */
    private function numeroUnique(BroadcastTarget $target): ?string
    {
        $numero = match ($target->type) {
            BroadcastMessage::TARGET_STAFF => $target->staffUserId
                ? User::find($target->staffUserId)?->smsNumber()
                : null,
            BroadcastMessage::TARGET_SINGLE_PATIENT => $target->patientId
                ? Patient::find($target->patientId)?->mobile
                : null,
            default => null,
        };

        return filled($numero) ? (string) $numero : null;
    }
}
