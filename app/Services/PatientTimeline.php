<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Patient;
use App\Models\PatientHistory;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Keneya\Dme\Models\Prescription;

/**
 * Frise chronologique unifiee d'un dossier patient (v3.2, point 3).
 *
 * Consultations, renvois, ordonnances, conclusions, paiements et pieces
 * jointes forment une seule suite triee par date, decoupee par passage —
 * jamais des blocs separes par nature de document. Un evenement ne peut donc
 * pas passer inapercu parce qu'il est range dans une autre liste.
 *
 * Deux sources alimentent la frise :
 *  - `patient_history`, deja chronologique et append-only ;
 *  - les pieces jointes deposees directement dans le dossier, qui n'ont par
 *    construction aucune entree d'historique en contexte.
 *
 * Le meme service alimente le panneau /service et la vue globale /admin, pour
 * que les deux roles lisent exactement la meme histoire.
 */
class PatientTimeline
{
    /**
     * @return array{episodes: Collection<int, array>, orphans: Collection<int, array>}
     */
    public function for(Patient $patient): array
    {
        $items = $this->historyItems($patient)
            ->merge($this->looseAttachmentItems($patient))
            // `seq` departage deux evenements de la meme seconde : l'ordre
            // d'ecriture reste l'ordre de lecture.
            ->sortBy([['at', 'asc'], ['seq', 'asc']])
            ->values();

        $episodes = $patient->visits
            ->sortByDesc('opened_at')
            ->values()
            ->map(fn (Visit $visit) => [
                'visit' => $visit,
                'entries' => $items->where('visit_id', $visit->getKey())->values(),
            ]);

        return [
            'episodes' => $episodes,
            // Lignes anterieures a l'introduction des passages, le cas echeant.
            'orphans' => $items->whereNull('visit_id')->values(),
        ];
    }

    /**
     * @return Collection<int, array>
     */
    private function historyItems(Patient $patient): Collection
    {
        $history = PatientHistory::query()
            ->with(['service', 'doctor.user', 'referral', 'attachments'])
            ->where('patient_id', $patient->getKey())
            ->orderBy('id')
            ->get();

        $prescriptions = $this->prescriptionsById($history);

        // `toBase()` : une fois transformees en tableaux, ces lignes ne sont
        // plus des modeles — une Collection Eloquent essaierait de les
        // dedoublonner par cle primaire a la fusion.
        return $history->toBase()->map(function (PatientHistory $entry) use ($prescriptions): array {
            return [
                'kind' => 'history',
                'at' => $entry->created_at,
                'seq' => $entry->getKey(),
                'visit_id' => $entry->visit_id,
                'type' => $entry->type,
                'label' => $entry->typeLabel(),
                'description' => $entry->description,
                'service' => $entry->service,
                'doctor' => $entry->doctor,
                'attachments' => $entry->attachments,
                'prescription' => $prescriptions[(int) $entry->dme_prescription_id] ?? null,
            ];
        });
    }

    /**
     * Les ordonnances que ces lignes d'historique designent, par identifiant.
     *
     * Jusqu'a la v3.3.1, `patient_history` ne portait pas de reference et la
     * frise appariait la n-ieme entree « ordonnance » d'un passage avec la
     * n-ieme ordonnance — un rapprochement par rang, exact tant que rien ne
     * manquait. La colonne `dme_prescription_id` a remplace ce calcul : la
     * ligne dit desormais laquelle.
     *
     * Une reference orpheline — le dossier medical purge, par exemple — se
     * lit comme une absence d'ordonnance, ce que la frise sait deja afficher.
     *
     * @param  EloquentCollection<int, PatientHistory>  $history
     * @return array<int, Prescription>
     */
    private function prescriptionsById(EloquentCollection $history): array
    {
        $ids = $history->pluck('dme_prescription_id')->filter()->unique()->all();

        if ($ids === []) {
            return [];
        }

        return Prescription::query()
            ->with(['doctor', 'items'])
            ->whereKey($ids)
            ->get()
            ->keyBy(fn (Prescription $prescription) => (int) $prescription->getKey())
            ->all();
    }

    /**
     * Pieces jointes deposees depuis le dossier lui-meme : elles n'ont ni
     * renvoi ni entree d'historique, mais doivent apparaitre dans la frise au
     * meme titre que les autres.
     *
     * @return Collection<int, array>
     */
    private function looseAttachmentItems(Patient $patient): Collection
    {
        return Attachment::query()
            ->with('visit.service')
            ->where('patient_id', $patient->getKey())
            ->whereNull('patient_history_id')
            ->orderBy('id')
            ->get()
            ->toBase()
            ->map(fn (Attachment $attachment) => [
                'kind' => 'attachment',
                'at' => $attachment->created_at,
                // Decale pour ne jamais entrer en collision avec un id
                // d'historique a la meme seconde.
                'seq' => $attachment->getKey() + 1_000_000,
                'visit_id' => $attachment->visit_id,
                'type' => 'attachment_added',
                'label' => 'Piece jointe ajoutee',
                'description' => $attachment->original_name,
                'service' => $attachment->visit?->service,
                'doctor' => null,
                'attachments' => collect([$attachment]),
                'prescription' => null,
            ]);
    }
}
