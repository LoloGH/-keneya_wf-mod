<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    public const KIND_CLINIQUE = 'clinique';

    public const KIND_PLATEAU_TECHNIQUE = 'plateau_technique';

    /**
     * Libelles francais des types de service, pour l'affichage.
     *
     * @var array<string, string>
     */
    public const KIND_LABELS = [
        self::KIND_CLINIQUE => 'Clinique',
        self::KIND_PLATEAU_TECHNIQUE => 'Plateau technique',
    ];

    protected $fillable = ['name', 'kind'];

    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class);
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function visitors(): HasMany
    {
        return $this->hasMany(Visitor::class);
    }

    public function incomingReferrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'to_service_id');
    }

    public function outgoingReferrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'from_service_id');
    }

    public function kindLabel(): string
    {
        return self::KIND_LABELS[$this->kind] ?? $this->kind;
    }
}
