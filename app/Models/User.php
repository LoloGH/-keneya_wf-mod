<?php

namespace App\Models;

use App\Support\Roles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Rattachement principal du medecin. Un medecin rattache a plusieurs
     * services possede plusieurs lignes `doctors` : voir doctors() et
     * doctorFor().
     */
    public function doctor(): HasOne
    {
        return $this->hasOne(Doctor::class);
    }

    /**
     * Tous les rattachements de ce medecin (cas rare du multi-service).
     */
    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class);
    }

    /**
     * Le rattachement de ce medecin au service donne, ou null s'il n'y est pas
     * rattache. C'est le seul point d'entree utilise par l'interface /service :
     * un medecin ne peut agir que dans un service qui lui appartient.
     */
    public function doctorFor(int $serviceId): ?Doctor
    {
        return $this->doctors()->where('service_id', $serviceId)->first();
    }

    public function receptionist(): HasOne
    {
        return $this->hasOne(Receptionist::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * Le role applicatif de l'utilisateur, parmi les trois roles cloisonnes.
     * Un utilisateur n'est cense en porter qu'un seul ; le premier reconnu fait foi.
     */
    public function scopedRole(): ?string
    {
        $names = $this->getRoleNames();

        foreach (Roles::all() as $role) {
            if ($names->contains($role)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * Route nommee de l'unique interface autorisee pour cet utilisateur.
     */
    public function homeRoute(): ?string
    {
        return Roles::homeRoute($this->scopedRole());
    }
}
