<?php

namespace App\Support;

/**
 * Les trois roles de KEneYa WorkFlow et l'unique interface associee a chacun.
 *
 * Regle de conception centrale : un role = une seule interface, aucune
 * navigation croisee. Toute redirection post-authentification et le middleware
 * EnsureRoleScope s'appuient exclusivement sur cette table.
 */
final class Roles
{
    public const ADMIN = 'admin';

    public const RECEPTIONIST = 'receptionist';

    public const DOCTOR = 'doctor';

    public const CASHIER = 'cashier';

    /**
     * @var array<string, string> role => route nommee de son interface unique
     */
    public const HOME_ROUTES = [
        self::ADMIN => 'admin.home',
        self::RECEPTIONIST => 'reception.home',
        self::DOCTOR => 'service.home',
        self::CASHIER => 'caisse.home',
    ];

    /**
     * @var array<string, string>
     */
    public const LABELS = [
        self::ADMIN => 'Administrateur',
        self::RECEPTIONIST => 'Receptionniste',
        self::DOCTOR => 'Medecin',
        self::CASHIER => 'Caissier',
    ];

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::ADMIN, self::RECEPTIONIST, self::DOCTOR, self::CASHIER];
    }

    public static function homeRoute(?string $role): ?string
    {
        return self::HOME_ROUTES[$role] ?? null;
    }

    public static function label(?string $role): string
    {
        return self::LABELS[$role] ?? '';
    }
}
