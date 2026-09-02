<?php

namespace App\Livewire\Admin;

use App\Services\FeedbackStatistics;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Ce que les sondages disent des services et des agents (section « Analyse »).
 *
 * Le composant ne calcule rien : tout vient de FeedbackStatistics, pour que la
 * meme moyenne ne soit jamais obtenue de deux facons differentes.
 *
 * Aucune action ici, et c'est voulu. Un tableau de bord qui note des personnes
 * doit se lire, pas agir : ni classement fige, ni alerte automatique, ni
 * couleur qui condamne. Le chiffre et son effectif, et c'est a l'humain de
 * conclure.
 */
class PerformanceDashboard extends Component
{
    /** Fenetre d'observation, en jours. Chaine vide = depuis le debut. */
    public string $periode = '90';

    public function render(FeedbackStatistics $stats): View
    {
        $jours = $this->periode === '' ? null : (int) $this->periode;

        return view('livewire.admin.performance-dashboard', [
            'entete' => $stats->entete($jours),
            'services' => $stats->parService($jours),
            'agents' => $stats->parAgent($jours),
            'postes' => $stats->parPoste($jours),
            'periodes' => FeedbackStatistics::PERIODES,
            'effectifFiable' => FeedbackStatistics::EFFECTIF_FIABLE,
        ]);
    }
}
