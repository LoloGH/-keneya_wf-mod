<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Keneya\Dme\Dme;
use Keneya\Dme\Models\Concerns\HasBusinessIdentifier;
use Keneya\Dme\Models\Concerns\RecordsMedicalActivity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Keneya\Dme\Database\Factories\ConsultationFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Consultation médicale (§19). Correspondance FHIR : Encounter (§44).
 */
class Consultation extends Model
{
    protected $table = 'dme_consultations';

    use HasBusinessIdentifier;
    use HasFactory;
    use RecordsMedicalActivity;
    use SoftDeletes;

    /**
     * Le module étant un package, la fabrique ne se devine pas par
     * convention : elle est désignée explicitement.
     */
    protected static function newFactory(): Factory
    {
        return ConsultationFactory::new();
    }

    protected $fillable = [
        'consultation_number', 'patient_id', 'doctor_id', 'service_id',
        'appointment_id', 'hospitalization_id', 'started_at', 'ended_at',
        'type', 'status', 'reason', 'history_of_illness', 'treatment_plan',
        'follow_up', 'recommendations',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /** @var array<string, string> */
    public const TYPES = [
        'ambulatory' => 'Consultation externe',
        'emergency' => 'Urgence',
        'follow_up' => 'Suivi',
        'inpatient' => 'Hospitalisation',
    ];

    /** @var array<string, string> */
    public const STATUSES = [
        'draft' => 'Brouillon',
        'in_progress' => 'En cours',
        'completed' => 'Terminée',
        'cancelled' => 'Annulée',
    ];

    public function identifierPrefixKey(): string
    {
        return 'consultation';
    }

    public function identifierColumn(): string
    {
        return 'consultation_number';
    }

    public function auditLabel(): string
    {
        return 'Consultation '.$this->consultation_number;
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Dme::userModel(), 'doctor_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function hospitalization(): BelongsTo
    {
        return $this->belongsTo(Hospitalization::class);
    }

    public function vitalSign(): HasOne
    {
        return $this->hasOne(VitalSign::class)->latestOfMany('measured_at');
    }

    public function vitalSigns(): HasMany
    {
        return $this->hasMany(VitalSign::class);
    }

    public function clinicalNotes(): HasMany
    {
        return $this->hasMany(ClinicalNote::class);
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function labOrders(): HasMany
    {
        return $this->hasMany(LabOrder::class);
    }

    public function imagingOrders(): HasMany
    {
        return $this->hasMany(ImagingOrder::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'in_progress'], true);
    }
}
