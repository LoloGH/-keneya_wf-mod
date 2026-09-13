<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Keneya\Dme\Dme;
use Keneya\Dme\Models\Concerns\RecordsMedicalActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Traitement habituel du patient (§18).
 * Correspondance FHIR : MedicationStatement (§44).
 */
class Medication extends Model
{
    protected $table = 'dme_medications';

    use HasFactory;
    use RecordsMedicalActivity;

    protected $fillable = [
        'patient_id', 'name', 'dosage', 'frequency', 'route',
        'started_on', 'ended_on', 'prescriber_id', 'status', 'comment',
    ];

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'ended_on' => 'date',
        ];
    }

    /** @var array<string, string> */
    public const STATUSES = [
        'active' => 'Actif',
        'suspended' => 'Suspendu',
        'stopped' => 'Arrêté',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function prescriber(): BelongsTo
    {
        return $this->belongsTo(Dme::userModel(), 'prescriber_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function auditLabel(): string
    {
        return 'Traitement habituel - '.$this->name;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
