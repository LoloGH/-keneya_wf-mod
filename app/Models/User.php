<?php

namespace App\Models;

use App\Support\Roles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'staff_type_id',
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

    public function cashier(): HasOne
    {
        return $this->hasOne(Cashier::class);
    }

    /**
     * Le rattachement de ce compte a un type de personnel generique
     * (v3.2.1, point 10). Nul pour les quatre roles fixes, qui ont leurs
     * propres tables.
     */
    public function staffMember(): HasOne
    {
        return $this->hasOne(StaffMember::class);
    }

    /**
     * Le type de personnel qui decrit ce compte (v3.2.2).
     *
     * Trois sources, dans cet ordre :
     *  1. le rattachement explicite (`users.staff_type_id`), choisi par l'admin
     *     quand plusieurs types partagent le meme role ;
     *  2. le type du personnel generique, porte par `staff_members` ;
     *  3. a defaut, le type d'origine du role — de sorte qu'un compte cree
     *     avant le v3.2.2, ou par un seeder, ait toujours un type.
     */
    public function staffType(): ?StaffType
    {
        if ($this->staff_type_id) {
            return $this->explicitStaffType()->first();
        }

        if ($type = $this->staffMember?->staffType) {
            return $type;
        }

        return ($role = $this->scopedRole())
            ? StaffType::where('matched_role', $role)->orderBy('id')->first()
            : null;
    }

    public function explicitStaffType(): BelongsTo
    {
        return $this->belongsTo(StaffType::class, 'staff_type_id');
    }

    /**
     * Raccourci de lecture des capacites, partage par les quatre interfaces
     * fixes et l'interface generique : une seule mecanique, pas deux.
     */
    public function hasCapability(string $capability): bool
    {
        return (bool) $this->staffType()?->can($capability);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * Ce compte est-il de garde sur ce service, maintenant ?
     *
     * S'appuie sur le planning deja en place plutot que sur une assignation
     * dediee : une rotation d'equipe se lit la, et nulle part ailleurs.
     */
    public function isOnDutyFor(int $serviceId, ?Carbon $moment = null): bool
    {
        $moment ??= now();

        return $this->schedules()
            ->whereDate('date', $moment->toDateString())
            ->where('service_id', $serviceId)
            ->whereTime('start_time', '<=', $moment->format('H:i:s'))
            ->whereTime('end_time', '>=', $moment->format('H:i:s'))
            ->exists();
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
     *
     * Nulle pour un type de personnel generique, dont l'interface se designe
     * par une URL portant son slug : voir homeUrl().
     */
    public function homeRoute(): ?string
    {
        return Roles::homeRoute($this->scopedRole());
    }

    /**
     * L'URL de l'unique interface autorisee, roles fixes et types generiques
     * confondus. C'est le point d'entree unique de toute redirection.
     */
    public function homeUrl(): ?string
    {
        if ($route = $this->homeRoute()) {
            return route($route);
        }

        return $this->staffType()?->homeUrl();
    }

    /**
     * Libelle affiche dans la barre de marque : le role fixe, ou le nom du
     * type de personnel.
     */
    public function roleLabel(): string
    {
        return Roles::label($this->scopedRole()) ?: (string) $this->staffType()?->name;
    }
}
