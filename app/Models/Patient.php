<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use HasFactory;

    public const STATUS_WAITING = 'waiting';

    public const STATUS_CALLED = 'called';

    /**
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        self::STATUS_WAITING => 'En attente',
        self::STATUS_CALLED => 'Appele',
    ];

    protected $fillable = [
        'patient_code',
        'name',
        'age',
        'gender',
        'mobile',
        'crno',
        'service_id',
        'token',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'age' => 'integer',
            'token' => 'integer',
        ];
    }

    /**
     * La file du jour d'un service.
     *
     * Un patient rejoint une file au moment ou son `service_id` et son `token`
     * sont ecrits — a l'enregistrement, puis a chaque renvoi. `updated_at`
     * marque donc son entree dans la file courante.
     *
     * Les files repartent a 1 chaque matin : ce scope est le seul endroit qui
     * definit « la file d'aujourd'hui », et il est partage par l'attribution
     * des tickets (TokenAllocator), la file du medecin et l'ecran de salle
     * d'attente — sinon un patient de la veille resterait affiche avec un
     * numero que le ticket du jour reattribuerait.
     */
    public function scopeInTodaysQueue(Builder $query, int $serviceId): Builder
    {
        return $query->where('service_id', $serviceId)
            ->whereDate('updated_at', today());
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(PatientHistory::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
