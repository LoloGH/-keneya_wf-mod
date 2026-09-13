<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Keneya\Dme\Models\MedicalDocument;
use Keneya\Dme\Services\Documents\DocumentStorage;

/**
 * Telechargement d'un document du dossier medical depuis le portail (v3.4).
 *
 * Memes garde-fous que l'ordonnance : le document doit appartenir au dossier
 * medical du patient designe par le jeton, et le code a quatre chiffres doit
 * avoir ete valide dans cette session. Sans cela, connaitre l'identifiant d'un
 * document suffirait a le lire.
 *
 * Un brouillon ou un document archive n'est pas servi, meme a la bonne
 * personne : ce que le portail refuse d'afficher, il refuse aussi de
 * telecharger. La regle vit ici plutot que dans la vue, une adresse se
 * devinant sans passer par la page.
 *
 * Le fichier n'est jamais servi depuis le systeme de fichiers : `storage_path`
 * pointe vers un disque prive, et c'est le service du module qui le lit.
 */
class PortalMedicalDocumentController extends Controller
{
    /** Les etats qu'un patient peut lire : ni brouillon, ni archive. */
    public const ETATS_LISIBLES = ['final', 'signed'];

    public function __invoke(
        Request $request,
        string $token,
        MedicalDocument $document,
        DocumentStorage $storage,
    ): Response {
        $patient = Patient::where('portal_token', $token)->firstOrFail();

        abort_unless($request->session()->get('portal.'.$patient->getKey()) === true, 403,
            "Saisissez d'abord votre code d'acces.");

        $dossier = $patient->dossierMedical();

        abort_unless($dossier && (int) $document->patient_id === (int) $dossier->getKey(), 404);
        abort_unless(in_array($document->status, self::ETATS_LISIBLES, true), 404);

        return response($storage->read($document), 200, [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => sprintf(
                'attachment; filename="%s"',
                // Le nom d'origine part tel quel dans un en-tete : les
                // guillemets et les sauts de ligne y sont retires, faute de
                // quoi un fichier bien nomme casserait la reponse.
                str_replace(['"', "\r", "\n"], '', $document->original_name ?: $document->document_number.'.pdf'),
            ),
        ]);
    }
}
