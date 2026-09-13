<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Support\Dme\PatientProjection;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Keneya\Dme\Models\Patient as DossierMedical;
use Keneya\Dme\Models\PatientIdentifier;
use Keneya\Dme\Services\Patients\PurgePatient;

/**
 * Les dossiers medicaux restes seuls apres la suppression de leur dossier
 * WorkFlow.
 *
 * Jusqu'ici, supprimer un dossier patient dans `/admin` ne touchait pas au
 * dossier medical du module : `dme_patients` gardait consultations,
 * ordonnances, analyses, imagerie et documents sur disque, rattaches a un
 * identifiant externe `keneya_workflow` qui ne designait plus rien. La
 * suppression emporte desormais les deux, mais elle ne peut rien pour ceux qui
 * ont ete crees avant : ils restent en base, et rien dans l'application ne les
 * montre.
 *
 * D'ou cette commande. Elle ne regarde qu'une chose : les identifiants
 * externes du systeme `keneya_workflow`, et si leur valeur correspond encore a
 * un `patients.patient_code`. Sinon, le dossier medical est orphelin.
 *
 * **Un dossier medical sans identifiant `keneya_workflow` n'est jamais
 * concerne.** Le module s'utilise aussi seul : un dossier ouvert directement
 * dans le DME, sans passage par l'accueil de WorkFlow, n'a pas de dossier
 * WorkFlow a avoir perdu. Il est absent de cette liste par construction, la
 * commande partant des identifiants et non des dossiers.
 *
 * Elle ne detruit rien tant qu'on ne le lui demande pas : sans `--force`, elle
 * affiche ce qu'elle a trouve et s'arrete. C'est l'inverse du reflexe habituel
 * d'un `--dry-run`, et c'est voulu — la commande efface des donnees de sante
 * sans retour possible, personne ne doit pouvoir le declencher en tapant son
 * nom pour voir.
 */
class PurgeDossiersMedicauxOrphelins extends Command
{
    protected $signature = 'keneya:dossiers-medicaux-orphelins
                            {--force : Supprime reellement les dossiers listes}
                            {--motif= : Motif inscrit au journal d\'audit}';

    protected $description = 'Liste, et sur --force supprime, les dossiers medicaux dont le dossier WorkFlow n\'existe plus';

    private const MOTIF_PAR_DEFAUT = 'Dossier WorkFlow supprime : reprise des dossiers medicaux restes orphelins.';

    public function handle(PurgePatient $purge): int
    {
        $suppression = (bool) $this->option('force');
        $motif = trim((string) ($this->option('motif') ?: self::MOTIF_PAR_DEFAUT));

        if ($motif === '') {
            $this->error('Le motif ne peut pas etre vide.');

            return self::FAILURE;
        }

        $orphelins = $this->orphelins();

        if ($orphelins->isEmpty()) {
            $this->info('Aucun dossier medical orphelin : chaque identifiant '
                .PatientProjection::SYSTEM.' designe un dossier WorkFlow existant.');

            return self::SUCCESS;
        }

        $this->table(
            ['Dossier medical', 'Identite', 'Dossier WorkFlow disparu', 'Consultations', 'Ordonnances', 'Documents'],
            $orphelins->map(fn (array $ligne) => [
                $ligne['dossier']->patient_number,
                $ligne['dossier']->fullName(),
                $ligne['identifiant'],
                $ligne['dossier']->consultations_count,
                $ligne['dossier']->prescriptions_count,
                $ligne['dossier']->documents_count,
            ])->all(),
        );

        if (! $suppression) {
            $this->warn(sprintf(
                '%d dossier(s) medical(aux) sans dossier WorkFlow. Rien n\'a ete supprime.',
                $orphelins->count(),
            ));
            $this->line('Relancez avec --force pour les supprimer definitivement.');

            return self::SUCCESS;
        }

        $barre = $this->output->createProgressBar($orphelins->count());

        foreach ($orphelins as $ligne) {
            // Par le service du module, jamais en ecrivant directement : c'est
            // lui qui journalise avant, emporte SMS et notifications que la
            // cascade ne prend pas, et n'efface les fichiers qu'une fois la
            // base tenue. Ecrire la sequence une seconde fois ici, c'est
            // l'ecrire differemment tot ou tard.
            $purge->purge(
                patient: $ligne['dossier'],
                reason: $motif,
                origin: sprintf(
                    'reprise des dossiers medicaux orphelins, dossier WorkFlow %s introuvable',
                    $ligne['identifiant'],
                ),
                properties: [
                    'patient_code_workflow' => $ligne['identifiant'],
                    'reprise' => 'keneya:dossiers-medicaux-orphelins',
                ],
            );

            $barre->advance();
        }

        $barre->finish();
        $this->newLine(2);
        $this->info(sprintf('%d dossier(s) medical(aux) supprime(s) definitivement.', $orphelins->count()));

        return self::SUCCESS;
    }

    /**
     * Les dossiers medicaux dont l'identifiant WorkFlow ne correspond a aucun
     * `patients.patient_code`.
     *
     * Le rapprochement se fait par lots plutot qu'une requete par dossier : un
     * etablissement en exploitation depuis quelques annees en compte des
     * milliers, et cette commande se lance sur la base de production.
     *
     * @return Collection<int, array{dossier: DossierMedical, identifiant: string}>
     */
    private function orphelins(): Collection
    {
        $orphelins = collect();

        PatientIdentifier::query()
            ->where('system', PatientProjection::SYSTEM)
            ->orderBy('id')
            ->chunkById(500, function ($lot) use ($orphelins): void {
                $codes = $lot->pluck('value')->filter()->unique();

                $connus = Patient::whereIn('patient_code', $codes)
                    ->pluck('patient_code')
                    ->flip();

                $manquants = $lot->reject(fn (PatientIdentifier $identifiant) => $connus->has($identifiant->value));

                if ($manquants->isEmpty()) {
                    return;
                }

                // `withTrashed()` : le dossier medical est en suppression
                // douce, et un dossier deja archive de cette facon garde tout
                // son contenu clinique en base. C'est precisement celui qu'il
                // ne faut pas manquer.
                $dossiers = DossierMedical::withTrashed()
                    ->withCount(['consultations', 'prescriptions', 'documents'])
                    ->whereIn('id', $manquants->pluck('patient_id'))
                    ->get()
                    ->keyBy('id');

                foreach ($manquants as $identifiant) {
                    $dossier = $dossiers->get($identifiant->patient_id);

                    if ($dossier === null) {
                        continue;
                    }

                    $orphelins->push([
                        'dossier' => $dossier,
                        'identifiant' => (string) $identifiant->value,
                    ]);
                }
            });

        return $orphelins;
    }
}
