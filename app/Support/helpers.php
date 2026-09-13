<?php

use App\Models\Setting;
use Illuminate\Database\QueryException;

if (! function_exists('hospital_name')) {
    /**
     * Nom de l'etablissement, modifiable en base par l'admin.
     *
     * Repli sur config/keneya.php tant qu'aucune valeur n'est enregistree :
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

if (! function_exists('asset_date')) {
    /**
     * URL d'un fichier de `public/`, suivie de la date de sa derniere
     * modification (v3.4).
     *
     * Sans cela, une feuille de style corrigee n'atteint pas les navigateurs
     * qui la tiennent deja : Nginx la sert sans `Cache-Control`, et le
     * navigateur applique alors sa propre duree de fraicheur, qui se compte en
     * heures. Le correctif est bien deploye, personne ne le voit, et le seul
     * remede connu de l'utilisateur est un rechargement force qu'il n'a aucune
     * raison de tenter.
     *
     * La date de modification plutot qu'un numero de version : elle change
     * toute seule au deploiement, et ne demande a personne de penser a
     * l'incrementer. Un fichier absent repart sans suffixe : une URL qui n'a
     * rien a versionner vaut mieux qu'une erreur en pleine page.
     */
    function asset_date(string $fichier): string
    {
        static $dates = [];

        if (! array_key_exists($fichier, $dates)) {
            $chemin = public_path($fichier);
            $dates[$fichier] = is_file($chemin) ? (string) filemtime($chemin) : null;
        }

        return $dates[$fichier] === null
            ? asset($fichier)
            : asset($fichier).'?v='.$dates[$fichier];
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
