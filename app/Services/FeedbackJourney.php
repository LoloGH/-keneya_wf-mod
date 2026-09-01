<?php

namespace App\Services;

use App\Models\PatientHistory;
use App\Models\Visit;
use Illuminate\Support\Collection;

/**
 * Les etapes reellement traversees par un patient (v3.2.9, point 3).
 *
 * Reconstituees depuis `patient_history`, et de nulle part ailleurs : ce
 * journal enregistre deja chaque passage avec son service et, quand elle est
 * identifiable, la personne qui a agi. Batir une seconde source aurait garanti
 * qu'elles divergent.
 *
 * Consequence directe et voulue : un sondage lance avant la cloture ne propose
 * que les etapes deja franchies. Ce n'est pas un cas particulier a traiter,
 * c'est ce que produit la lecture du journal a cet instant.
 */
class FeedbackJourney
{
    /**
     * Les postes rencontres, dans l'ordre de la premiere rencontre.
     *
     * Un meme medecin vu trois fois ne fait qu'une etape : on demande au
     * patient de noter des personnes et des guichets, pas des evenements.
     *
     * @return Collection<int, array{key: string, label: string, user_id: ?int, service_id: ?int}>
     */
    public function steps(?Visit $visit): Collection
    {
        if (! $visit) {
            return new Collection;
        }

        $lignes = PatientHistory::query()
            ->with(['service', 'doctor.user', 'staffMember.user'])
            ->where('visit_id', $visit->getKey())
            ->orderBy('id')
            ->get();

        $etapes = [];

        foreach ($lignes as $ligne) {
            $agent = $ligne->doctor?->user ?? $ligne->staffMember?->user;
            $service = $ligne->service?->name;

            // Une ligne sans service ni agent ne designe aucun poste : la
            // proposer reviendrait a demander de noter le vide.
            if ($service === null && $agent === null) {
                continue;
            }

            $cle = ($ligne->service_id ?? 0).':'.($agent?->getKey() ?? 0);

            if (isset($etapes[$cle])) {
                continue;
            }

            $etapes[$cle] = [
                'key' => $cle,
                'label' => $this->libelle($service, $agent?->name),
                'user_id' => $agent?->getKey(),
                'service_id' => $ligne->service_id,
            ];
        }

        return new Collection(array_values($etapes));
    }

    /**
     * « Medecine Generale — Dr Traore » quand on sait qui, « Accueil » quand
     * seul le poste est connu.
     */
    private function libelle(?string $service, ?string $agent): string
    {
        if ($service !== null && $agent !== null) {
            return $service.' — '.$agent;
        }

        return $service ?? (string) $agent;
    }
}
