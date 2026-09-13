<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Keneya\Dme\Dme;
use Keneya\Dme\Models\Concerns\HasBusinessIdentifier;
use Keneya\Dme\Models\Concerns\RecordsMedicalActivity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Keneya\Dme\Database\Factories\LabOrderFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/** Demande d'examens de laboratoire (§23). FHIR : ServiceRequest (§44). */
class LabOrder extends Model
{
    protected $table = 'dme_lab_orders';

    use HasBusinessIdentifier;
    use HasFactory;
    use RecordsMedicalActivity;

    /**
     * Le module étant un package, la fabrique ne se devine pas par
     * convention : elle est désignée explicitement.
     */
    protected static function newFactory(): Factory
    {
        return LabOrderFactory::new();
    }

    protected $fillable = [
        'order_number', 'patient_id', 'consultation_id', 'doctor_id',
        'requested_at', 'priority', 'indication', 'conclusion', 'status', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @var array<string, string> */
    public const STATUSES = [
        'requested' => 'Demandé',
        'in_progress' => 'En cours',
        'available' => 'Disponible',
        'validated' => 'Validé',
        'cancelled' => 'Annulé',
    ];

    /** @var array<string, string> */
    public const PRIORITIES = [
        'routine' => 'Normale',
        'urgent' => 'Urgente',
        'vital' => 'Vitale',
    ];

    public function identifierPrefixKey(): string
    {
        return 'lab_order';
    }

    public function identifierColumn(): string
    {
        return 'order_number';
    }

    public function auditLabel(): string
    {
        return 'Demande de laboratoire '.$this->order_number;
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Dme::userModel(), 'doctor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LabOrderItem::class);
    }

    public function results(): HasManyThrough
    {
        return $this->hasManyThrough(LabResult::class, LabOrderItem::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }
}
