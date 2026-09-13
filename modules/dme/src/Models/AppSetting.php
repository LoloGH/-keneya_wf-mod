<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Réglage clé/valeur modifiable en base. Voir la migration
 * create_app_settings_table pour le rôle de cette table :
 * AppServiceProvider en charge le contenu au démarrage pour surcharger
 * config('dme.*') : les modèles et contrôleurs ne la consultent
 * jamais directement, ils continuent de lire config().
 */
class AppSetting extends Model
{
    protected $table = 'dme_app_settings';

    protected $fillable = ['key', 'value'];

    /**
     * Enregistre (ou efface, si $value est vide) une valeur.
     */
    public static function put(string $key, ?string $value): void
    {
        $value = $value !== null && trim($value) === '' ? null : $value;

        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Toutes les valeurs enregistrées, indexées par clé.
     *
     * @return array<string, string|null>
     */
    public static function map(): array
    {
        return static::query()->pluck('value', 'key')->all();
    }
}
