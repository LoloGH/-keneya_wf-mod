<?php

namespace App\Services;

use App\Models\FeedbackEntry;
use App\Models\FeedbackSurveyRating;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ce que les sondages disent des services et des agents.
 *
 * Toutes les agregations de l'application vivent ici, et nulle part ailleurs :
 * la meme moyenne calculee a deux endroits finit par donner deux chiffres, et
 * c'est alors le tableau de bord qu'on cesse de croire.
 *
 * **Un chiffre qui compte des personnes se manie autrement qu'un chiffre qui
 * compte des tickets.** Une moyenne sur deux notes n'est pas une performance,
 * c'est un hasard : chaque ligne rend donc son effectif, et `EFFECTIF_FIABLE`
 * dit a partir de quand la moyenne merite d'etre lue. Les vues s'en servent
 * pour le signaler — jamais pour cacher la ligne, ce qui reviendrait a choisir
 * a la place du lecteur.
 */
class FeedbackStatistics
{
    /**
     * En deca, la moyenne est affichee mais signalee comme peu fiable.
     *
     * Cinq n'a rien d'une verite statistique ; c'est le seuil en dessous duquel
     * un seul avis pese plus de vingt pour cent du resultat, ce qu'aucune
     * lecture honnete ne peut ignorer.
     */
    public const EFFECTIF_FIABLE = 5;

    /** Periodes offertes, en jours. `null` vaut « depuis le debut ». */
    public const PERIODES = [
        '30' => '30 derniers jours',
        '90' => '3 derniers mois',
        '365' => '12 derniers mois',
        '' => 'Depuis le debut',
    ];

    private function depuis(?int $jours): ?Carbon
    {
        return $jours ? now()->subDays($jours) : null;
    }

    /**
     * Les chiffres de tete : volume de retours et satisfaction d'ensemble.
     *
     * @return array{sondages: int, note_globale: ?float, reclamations: int, constats: int, notes_par_poste: int}
     */
    public function entete(?int $jours = null): array
    {
        $depuis = $this->depuis($jours);

        $compter = fn (string $type) => FeedbackEntry::where('type', $type)
            ->when($depuis, fn ($q) => $q->where('created_at', '>=', $depuis))
            ->count();

        $moyenne = FeedbackEntry::where('type', FeedbackEntry::TYPE_SURVEY)
            ->whereNotNull('rating_care')
            ->when($depuis, fn ($q) => $q->where('created_at', '>=', $depuis))
            ->avg('rating_care');

        return [
            'sondages' => $compter(FeedbackEntry::TYPE_SURVEY),
            'note_globale' => $moyenne === null ? null : round((float) $moyenne, 2),
            'reclamations' => $compter(FeedbackEntry::TYPE_COMPLAINT),
            'constats' => $compter(FeedbackEntry::TYPE_INCIDENT),
            'notes_par_poste' => FeedbackSurveyRating::query()
                ->when($depuis, fn ($q) => $q->where('created_at', '>=', $depuis))
                ->count(),
        ];
    }

    /**
     * Par service : ce que les patients disent de leur prise en charge.
     *
     * Les services sans un seul retour figurent quand meme, a zero. Les faire
     * disparaitre donnerait un classement flatteur ou ne resteraient que les
     * services dont quelqu'un a parle.
     *
     * @return Collection<int, array{nom: string, sondages: int, moyenne: ?float, reclamations: int, fiable: bool}>
     */
    public function parService(?int $jours = null): Collection
    {
        $depuis = $this->depuis($jours);

        $agrege = FeedbackEntry::query()
            ->when($depuis, fn ($q) => $q->where('created_at', '>=', $depuis))
            ->whereNotNull('service_id')
            ->selectRaw('service_id')
            ->selectRaw('SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) AS sondages', [FeedbackEntry::TYPE_SURVEY])
            ->selectRaw('SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) AS reclamations', [FeedbackEntry::TYPE_COMPLAINT])
            ->selectRaw('AVG(CASE WHEN type = ? THEN rating_care END) AS moyenne', [FeedbackEntry::TYPE_SURVEY])
            ->groupBy('service_id')
            ->get()
            ->keyBy('service_id');

        return Service::orderBy('name')->get()
            ->map(function (Service $service) use ($agrege) {
                $ligne = $agrege->get($service->getKey());
                $sondages = (int) ($ligne->sondages ?? 0);

                return [
                    'nom' => $service->name,
                    'sondages' => $sondages,
                    'moyenne' => $ligne?->moyenne === null ? null : round((float) $ligne->moyenne, 2),
                    'reclamations' => (int) ($ligne->reclamations ?? 0),
                    'fiable' => $sondages >= self::EFFECTIF_FIABLE,
                ];
            })
            ->values();
    }

    /**
     * Par agent : les notes que les patients ont donnees a chaque personne
     * rencontree durant leur parcours.
     *
     * La source est `feedback_survey_ratings` et non la note globale du
     * passage : c'est la seule qui designe quelqu'un nommement. Une moyenne de
     * service attribuee a chacun de ses agents accuserait l'infirmier du temps
     * d'attente a la caisse.
     *
     * Seuls les agents reellement notes figurent : afficher a zero ceux que
     * personne n'a rencontres les ferait passer pour mal notes.
     *
     * @return Collection<int, array{nom: string, role: string, notes: int, moyenne: float, fiable: bool}>
     */
    public function parAgent(?int $jours = null): Collection
    {
        $depuis = $this->depuis($jours);

        $lignes = FeedbackSurveyRating::query()
            ->whereNotNull('user_id')
            ->when($depuis, fn ($q) => $q->where('created_at', '>=', $depuis))
            ->selectRaw('user_id, COUNT(*) AS notes, AVG(rating) AS moyenne')
            ->groupBy('user_id')
            ->get();

        if ($lignes->isEmpty()) {
            return collect();
        }

        $agents = User::whereIn('id', $lignes->pluck('user_id'))->get()->keyBy('id');

        return $lignes
            ->map(function ($ligne) use ($agents) {
                $agent = $agents->get($ligne->user_id);
                $notes = (int) $ligne->notes;

                return [
                    'nom' => $agent?->name ?? 'Compte supprime',
                    'role' => $agent?->roleLabel() ?? '—',
                    'notes' => $notes,
                    'moyenne' => round((float) $ligne->moyenne, 2),
                    'fiable' => $notes >= self::EFFECTIF_FIABLE,
                ];
            })
            // Les effectifs solides d'abord : une moyenne de 5,0 sur une seule
            // note ne doit pas trôner en tête d'un classement de personnes.
            ->sortByDesc(fn (array $a) => [$a['fiable'], $a['moyenne'], $a['notes']])
            ->values();
    }

    /**
     * Les postes les plus notes, tous agents confondus.
     *
     * `post_label` porte le libelle vu par le patient (« Caisse », « Medecin —
     * Dr X »). Il repond a une autre question que `parAgent` : non pas qui,
     * mais quelle etape du parcours pese sur la satisfaction.
     *
     * @return Collection<int, array{poste: string, notes: int, moyenne: float, fiable: bool}>
     */
    public function parPoste(?int $jours = null): Collection
    {
        $depuis = $this->depuis($jours);

        return FeedbackSurveyRating::query()
            ->when($depuis, fn ($q) => $q->where('created_at', '>=', $depuis))
            ->selectRaw('post_label, COUNT(*) AS notes, AVG(rating) AS moyenne')
            ->groupBy('post_label')
            ->get()
            ->map(fn ($ligne) => [
                'poste' => (string) $ligne->post_label,
                'notes' => (int) $ligne->notes,
                'moyenne' => round((float) $ligne->moyenne, 2),
                'fiable' => (int) $ligne->notes >= self::EFFECTIF_FIABLE,
            ])
            ->sortByDesc(fn (array $a) => [$a['fiable'], $a['moyenne'], $a['notes']])
            ->values();
    }
}
