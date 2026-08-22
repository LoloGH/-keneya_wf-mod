<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Reglages modifiables en base, sans redeploiement.
 *
 * Les valeurs sont mises en cache : elles sont lues a chaque rendu de page
 * (nom de l'hopital dans la barre de navigation) et changent tres rarement.
 */
class Setting extends Model
{
    public const HOSPITAL_NAME = 'hospital_name';

    protected $fillable = ['key', 'value'];

    protected static function booted(): void
    {
        static::saved(fn (Setting $setting) => Cache::forget(self::cacheKey($setting->key)));
        static::deleted(fn (Setting $setting) => Cache::forget(self::cacheKey($setting->key)));
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return Cache::rememberForever(
            self::cacheKey($key),
            fn () => static::query()->where('key', $key)->value('value'),
        ) ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    private static function cacheKey(string $key): string
    {
        return 'settings:'.$key;
    }
}
