<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Keneya\Dme\Contracts\DmeUser;
use Keneya\Dme\Database\Factories\UserFactory;
use Keneya\Dme\Models\Concerns\IsDmePractitioner;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Professionnel de santé ou agent administratif.
 *
 * Correspondance FHIR visée : Practitioner (§44).
 *
 * Ce modèle ne sert que lorsque le module tourne seul. Monté dans une
 * application hôte, c'est le modèle utilisateur de l'hôte qui fait foi :
 * il partage la même table `users` et reçoit les mêmes capacités par le
 * trait {@see \Keneya\Dme\Models\Concerns\IsDmePractitioner}. Le nom de
 * classe à utiliser est lu dans `config('dme.models.user')`.
 */
class User extends Authenticatable implements DmeUser
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use IsDmePractitioner;
    use Notifiable;

    /**
     * Le module étant un package, la fabrique ne se devine pas par
     * convention : elle est désignée explicitement.
     */
    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }

    protected $fillable = [
        'matricule', 'name', 'first_name', 'last_name', 'title', 'speciality',
        'email', 'phone', 'password', 'service_id', 'is_active',
        'is_on_duty', 'on_duty_since',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_on_duty' => 'boolean',
            'on_duty_since' => 'datetime',
        ];
    }
}
