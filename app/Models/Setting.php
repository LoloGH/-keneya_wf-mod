<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
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
    use RecordsActivity;

    public const HOSPITAL_NAME = 'hospital_name';

    /**
     * Delai, en minutes, entre le rappel et l'heure du rendez-vous
     * (v3.2.3, point 2). Reglable par l'etablissement plutot que code en dur :
     * une consultation programmee ne se prepare pas comme un bloc.
     */
    public const APPOINTMENT_REMINDER_MINUTES = 'appointment_reminder_minutes';

    /** Valeur retenue tant que l'administrateur n'a rien choisi. */
    public const DEFAULT_APPOINTMENT_REMINDER_MINUTES = 60;

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

    public static function auditLabel(): string
    {
        return 'Reglage';
    }
}
