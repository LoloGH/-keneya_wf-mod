<?php

namespace App\Livewire\Admin;

use App\Models\Attachment;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Visit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Livewire\Component;

/**
 * Etat de la sauvegarde (refonte visuelle, groupe « Systeme »).
 *
 * Volontairement une page de constat, et non un bouton « sauvegarder
 * maintenant ». Une sauvegarde declenchee depuis le navigateur ecrirait son
 * archive dans le conteneur applicatif — c'est-a-dire a l'endroit exact que la
 * sauvegarde est censee proteger, et qui disparait avec lui. Le script
 * `scripts/backup.sh` tourne cote systeme, sous un compte capable de lire les
 * fichiers deposes par le serveur web, et ecrit ou l'exploitant a decide.
 *
 * Ce que cette page apporte : dire ce qui est en jeu. Un administrateur qui
 * voit « 1 284 dossiers patients, 340 Mo de pieces jointes, 3 signatures » sait
 * ce qu'il perd si la sauvegarde n'a pas tourne. Un ecran qui affirmerait
 * « sauvegarde OK » sans rien pouvoir verifier serait pire que rien.
 */
class BackupStatus extends Component
{
    /**
     * Poids d'un dossier de stockage, en octets. Renvoie null si le dossier
     * n'existe pas encore — ce qui n'est pas une anomalie : le dossier des
     * signatures n'apparait qu'au premier depot.
     */
    private function poidsDossier(string $chemin): ?int
    {
        if (! File::isDirectory($chemin)) {
            return null;
        }

        $total = 0;

        foreach (File::allFiles($chemin) as $fichier) {
            $total += $fichier->getSize();
        }

        return $total;
    }

    public function render(): View
    {
        return view('livewire.admin.backup-status', [
            'patients' => Patient::count(),
            'visites' => Visit::count(),
            'ordonnances' => Prescription::count(),
            'piecesJointes' => Attachment::count(),
            'poidsPieces' => $this->poidsDossier(storage_path('app/attachments')),
            'poidsSignatures' => $this->poidsDossier(storage_path('app/signatures')),
        ]);
    }
}
