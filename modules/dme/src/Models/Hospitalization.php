<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Keneya\Dme\Dme;
use Keneya\Dme\Models\Concerns\HasBusinessIdentifier;
use Keneya\Dme\Models\Concerns\RecordsMedicalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Séjour hospitalier (§25).
 * Correspondance FHIR : Encounter de classe « inpatient » (§44).
 */
class Hospitalization extends Model
{
    protected $table = 'dme_hospitalizations';

    use HasBusinessIdentifier;
    use HasFactory;
    use RecordsMedicalActivity;
    use SoftDeletes;

    protected $fillable = [
        'hospitalization_number', 'patient_id', 'service_id', 'doctor_id',
        'admitted_at', 'admission_reason', 'admission_diagnosis', 'room', 'bed',
        'discharged_at', 'discharge_diagnosis', 'discharge_treatment',
        'discharge_recommendations', 'discharge_summary', 'discharge_type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'admitted_at' => 'datetime',
            'discharged_at' => 'datetime',
        ];
    }

    /** @var array<string, string> */
    public const STATUSES = [
        'admitted' => 'En cours',
        'discharged' => 'Sortie',
        'transferred' => 'Transféré',
        'cancelled' => 'Annulée',
    ];

    public function identifierPrefixKey(): string
    {
        return 'hospitalization';
    }

    public function identifierColumn(): string
    {
        return 'hospitalization_number';
    }

    public function auditLabel(): string
    {
        return 'Hospitalisation '.$this->hospitalization_number;
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Dme::userModel(), 'doctor_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(HospitalizationEvent::class)->orderBy('occurred_at');
    }

    public function nursingNotes(): HasMany
    {
        return $this->hasMany(NursingNote::class)->orderByDesc('occurred_at');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Durée du séjour en jours, en cours ou close. */
    public function lengthOfStay(): int
    {
        return (int) $this->admitted_at->diffInDays($this->discharged_at ?? now());
    }

    public function isOngoing(): bool
    {
        return $this->status === 'admitted';
    }
}
