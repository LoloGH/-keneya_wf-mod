<?php

use App\Models\Setting;
use Illuminate\Database\QueryException;

if (! function_exists('hospital_name')) {
    /**
     * Nom de l'etablissement, modifiable en base par l'admin.
     *
     * Repli sur config/keneya.php tant qu'aucune valeur n'est enregistree —
     * et aussi pendant les migrations, ou la table `settings` peut ne pas
     * encore exister.
     */
    function hospital_name(): string
    {
        $fallback = (string) config('keneya.hospital');

        try {
            return Setting::get(Setting::HOSPITAL_NAME) ?: $fallback;
        } catch (QueryException) {
            return $fallback;
        }
    }
}

if (! function_exists('notification_sound_url')) {
    /**
     * URL du son de notification, ou null si aucun fichier n'est fourni
     * (v3.2.3, point 2).
     *
     * Le premier format present gagne. La cloche livree avec le depot est un
     * WAV : deposer `public/sounds/notification.mp3` la remplace sans toucher
     * une ligne de code, comme pour le logo. Aucun fichier du tout : la cloche
     * reste muette, sans erreur.
     */
    function notification_sound_url(): ?string
    {
        foreach (['mp3', 'ogg', 'wav'] as $extension) {
            $fichier = 'sounds/notification.'.$extension;

            if (is_file(public_path($fichier))) {
                return asset($fichier);
            }
        }

        return null;
    }
}
