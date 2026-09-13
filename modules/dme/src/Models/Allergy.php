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
 * Allergie (§17). Correspondance FHIR : AllergyIntolerance (§44).
 *
 * Une allergie sévère active remonte automatiquement dans les alertes
 * permanentes du dossier (§13) et dans le contrôle de prescription (§22).
 */
class Allergy extends Model
{
    use HasFactory;
    use RecordsMedicalActivity;

    protected $table = 'dme_allergies';

    protected $fillable = [
        'patient_id', 'allergen', 'allergen_type', 'reaction', 'severity',
        'observed_on', 'status', 'comment', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['observed_on' => 'date'];
    }

    /** @var array<string, string> */
    public const SEVERITIES = [
        'mild' => 'Faible',
        'moderate' => 'Modérée',
        'severe' => 'Sévère',
        'unknown' => 'Inconnue',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(Dme::userModel(), 'recorded_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function severityLabel(): string
    {
        return self::SEVERITIES[$this->severity] ?? $this->severity;
    }

    public function isCritical(): bool
    {
        return $this->status === 'active' && $this->severity === 'severe';
    }

    public function auditLabel(): string
    {
        return 'Allergie '.$this->allergen;
    }
}
