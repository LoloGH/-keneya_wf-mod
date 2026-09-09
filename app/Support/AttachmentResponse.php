<?php

namespace App\Support;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Comment une piece jointe est servie (v3.3.1).
 *
 * Elle l'etait toujours en telechargement. Un compte rendu se **regarde** :
 * obliger a le telecharger pour le lire laisse une copie du dossier sur chaque
 * poste qui l'a consulte — dans un hopital, sur des postes partages. Un PDF ou
 * une image s'affiche donc dans le navigateur, et le telechargement reste
 * offert a qui en a vraiment besoin (`?telecharger=1`).
 *
 * Trois routes servent les pieces jointes — medecin, administration, portail
 * patient — avec chacune ses propres controles d'acces. La reponse, elle, doit
 * etre la meme partout : la voici en un seul endroit, pour qu'un durcissement
 * d'en-tete ne s'applique pas a deux roles sur trois.
 */
final class AttachmentResponse
{
    /**
     * Le disque des pieces jointes de WorkFlow. Distinct de celui du dossier
     * medical, qui a sa propre route controlee dans le module.
     */
    private const DISQUE = 'attachments';

    /**
     * `$forcerLeTelechargement` repond au choix explicite de l'utilisateur ;
     * a defaut, tout ce qui se regarde s'affiche.
     */
    public static function for(Attachment $attachment, bool $forcerLeTelechargement = false): StreamedResponse
    {
        $disque = Storage::disk(self::DISQUE);

        abort_unless($disque->exists($attachment->path), 404);

        if ($forcerLeTelechargement || ! $attachment->isPreviewable()) {
            return $disque->download($attachment->path, $attachment->original_name);
        }

        return $disque->response($attachment->path, $attachment->original_name, array_filter([
            'Content-Type' => $attachment->mime_type,
            // Le navigateur ne doit pas deviner un type qu'on ne lui a pas
            // annonce : c'est ce qui transformerait un fichier innocent en
            // page executee sur le domaine de l'application.
            'X-Content-Type-Options' => 'nosniff',
            // Le bac a sable est reserve aux images. Un PDF est rendu par le
            // lecteur integre du navigateur, qui execute ses propres scripts :
            // le lui interdire n'afficherait plus rien.
            'Content-Security-Policy' => $attachment->isImage()
                ? "default-src 'none'; img-src 'self'; sandbox"
                : null,
        ], static fn ($valeur) => $valeur !== null), 'inline');
    }
}
