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
