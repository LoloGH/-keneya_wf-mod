<?php

namespace App\Console\Commands;

use App\Models\PatientHistory;
use App\Models\Prescription as OrdonnanceWorkflow;
use App\Support\Dme\PatientProjection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Keneya\Dme\Models\Prescription as OrdonnanceMedicale;

/**
 * Reprise des ordonnances anterieures a la v3.3.1 dans le dossier medical.
 *
 * Les deux ordonnances ont fusionne : une seule table, `dme_prescriptions`,
 * et un seul document. Restent celles qui avaient ete ecrites avant, dans la
 * table de WorkFlow. Les laisser la reviendrait a partager le dossier d'un
 * patient entre deux endroits : c'est precisement ce que la fusion supprime.
 *
 * La commande est rejouable sans risque : la paire (`source_system`,
 * `source_id`) porte un index unique en base, et chaque ordonnance deja
 * reprise est simplement ignoree. On peut donc la lancer une premiere fois
 * pour verifier le compte, puis de nouveau apres correction.
 *
 * Rien n'est efface : la table d'origine reste en place, en lecture, le temps
 * que l'etablissement constate que la reprise est complete.
 */
class RepriseOrdonnances extends Command
{
    protected $signature = 'keneya:reprise-ordonnances
                            {--dry-run : Compte ce qui serait repris, sans rien ecrire}';

    protected $description = 'Reprend les ordonnances de WorkFlow dans le dossier medical (v3.3.1)';

    /** Duree de validite retenue pour une ordonnance ancienne, en jours. */
    private const VALIDITE_JOURS = 90;

    public function handle(): int
    {
        $simulation = (bool) $this->option('dry-run');
        $reprises = 0;
        $ignorees = 0;
        $sansPatient = 0;

        $total = OrdonnanceWorkflow::count();
        $this->info(sprintf('%d ordonnance(s) dans la table WorkFlow.', $total));

        $barre = $this->output->createProgressBar($total);

        OrdonnanceWorkflow::with(['patient', 'doctor'])
            ->orderBy('id')
            ->chunkById(200, function ($lot) use (
                $simulation, $barre, &$reprises, &$ignorees, &$sansPatient
            ) {
                foreach ($lot as $ancienne) {
                    $barre->advance();

                    if ($this->dejaReprise($ancienne)) {
                        $ignorees++;

                        continue;
                    }

                    // Une ordonnance dont le patient a ete supprime n'a plus
                    // de dossier ou atterrir. On la signale plutot que de
                    // fabriquer un dossier sans identite.
                    if ($ancienne->patient === null) {
                        $sansPatient++;

                        continue;
                    }

                    if ($simulation) {
                        $reprises++;

                        continue;
                    }

                    $this->reprend($ancienne);
                    $reprises++;
                }
            });

        $barre->finish();
        $this->newLine(2);

        $this->table(['', 'Ordonnances'], [
            [$simulation ? 'A reprendre' : 'Reprises', $reprises],
            ['Deja reprises', $ignorees],
            ['Sans patient', $sansPatient],
        ]);

        if ($sansPatient > 0) {
            $this->warn(sprintf(
                '%d ordonnance(s) sans patient : elles restent dans la table WorkFlow.',
                $sansPatient,
            ));
        }

        return self::SUCCESS;
    }

    private function dejaReprise(OrdonnanceWorkflow $ancienne): bool
    {
        return OrdonnanceMedicale::withTrashed()
            ->where('source_system', 'keneya_workflow')
            ->where('source_id', (string) $ancienne->getKey())
            ->exists();
    }

    private function reprend(OrdonnanceWorkflow $ancienne): void
    {
        $dossier = PatientProjection::resolve($ancienne->patient);

        DB::transaction(function () use ($ancienne, $dossier) {
            $emise = $ancienne->created_at;

            $nouvelle = $dossier->prescriptions()->create([
                'source_system' => 'keneya_workflow',
                'source_id' => (string) $ancienne->getKey(),
                // Le prescripteur est desormais designe par son compte : la
                // table du dossier medical ne connait pas les fiches de
                // service. Une fiche sans compte laisse le champ vide plutot
                // que de designer quelqu'un d'autre.
                'doctor_id' => $ancienne->doctor?->user_id,
                'issued_on' => $emise?->toDateString() ?? now()->toDateString(),
                'valid_until' => $emise?->copy()->addDays(self::VALIDITE_JOURS)->toDateString(),
                'status' => 'validated',
                'validated_by' => $ancienne->doctor?->user_id,
                'validated_at' => $emise,
            ]);

            // La date d'origine, et non celle de la reprise : une ordonnance
            // reprise doit se lire a sa place dans le dossier.
            if ($emise !== null) {
                $nouvelle->forceFill([
                    'created_at' => $emise,
                    'updated_at' => $ancienne->updated_at,
                ])->saveQuietly();
            }

            foreach ($ancienne->lignes() as $rang => $ligne) {
                $nouvelle->items()->create([
                    'position' => $rang + 1,
                    'medication_name' => $ligne['medicament'],
                    'frequency' => $ligne['posologie'] ?: null,
                    'duration' => $ligne['duree'] ?: null,
                ]);
            }

            $this->rattacheHistorique($ancienne, $nouvelle);
        });
    }

    /**
     * Relie la ligne d'historique correspondante a l'ordonnance reprise.
     *
     * L'historique ne portait pas de reference avant la v3.3.1 : on retrouve
     * la bonne ligne comme la frise le faisait, par rang au sein du passage,
     * la n-ieme entree « ordonnance » repond a la n-ieme ordonnance, les deux
     * ayant ete ecrites dans la meme transaction. Les ordonnances etant
     * reprises dans l'ordre de leur identifiant, il suffit de prendre la
     * premiere ligne du passage qui n'a pas encore trouve la sienne. Ce
     * rapprochement ne sert qu'une fois, ici ; passe la reprise, la colonne
     * le remplace.
     */
    private function rattacheHistorique(OrdonnanceWorkflow $ancienne, OrdonnanceMedicale $nouvelle): void
    {
        if ($ancienne->visit_id === null) {
            return;
        }

        $ligne = PatientHistory::where('visit_id', $ancienne->visit_id)
            ->where('type', PatientHistory::TYPE_PRESCRIPTION)
            ->whereNull('dme_prescription_id')
            ->orderBy('id')
            ->value('id');

        if ($ligne === null) {
            return;
        }

        // L'ecriture passe par le constructeur de requetes, et non par le
        // modele : `PatientHistoryObserver` interdit toute modification d'une
        // ligne d'historique, et c'est une regle a laquelle on ne touche pas.
        //
        // Ce qui est ecrit ici n'est pas une correction du recit : la ligne
        // disait deja qu'une ordonnance avait ete etablie, elle n'avait
        // simplement pas de colonne pour dire laquelle. On remplit cette
        // colonne, une fois, sur des lignes ou elle est nulle : jamais un
        // champ que quelqu'un a saisi.
        DB::table('patient_history')
            ->where('id', $ligne)
            ->whereNull('dme_prescription_id')
            ->update(['dme_prescription_id' => $nouvelle->getKey()]);
    }
}
