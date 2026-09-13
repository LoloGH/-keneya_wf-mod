<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Keneya\Dme\Dme;
use Keneya\Dme\Models\Concerns\RecordsMedicalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Résultat d'analyse (§23). Correspondance FHIR : Observation dans un
 * DiagnosticReport (§44).
 *
 * La valeur de référence est copiée sur la ligne : un résultat reste
 * interprétable même si le référentiel du laboratoire évolue plus tard.
 */
class LabResult extends Model
{
    protected $table = 'dme_lab_results';

    use HasFactory;
    use RecordsMedicalActivity;

    protected $fillable = [
        'lab_order_item_id', 'patient_id', 'parameter', 'value', 'unit',
        'reference_range', 'flag', 'comment', 'measured_at',
        'performed_by', 'validated_by', 'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'measured_at' => 'datetime',
            'validated_at' => 'datetime',
        ];
    }

    /** @var array<string, string> */
    public const FLAGS = [
        'normal' => 'Normal',
        'low' => 'Bas',
        'high' => 'Élevé',
        'critical' => 'Critique',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(LabOrderItem::class, 'lab_order_item_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(Dme::userModel(), 'validated_by');
    }

    public function flagLabel(): string
    {
        return self::FLAGS[$this->flag] ?? $this->flag;
    }

    public function isAbnormal(): bool
    {
        return $this->flag !== 'normal';
    }

    public function auditLabel(): string
    {
        return 'Résultat '.$this->parameter;
    }
}
