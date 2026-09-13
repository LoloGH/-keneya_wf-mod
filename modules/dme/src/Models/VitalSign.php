<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Keneya\Dme\Dme;
use Keneya\Dme\Models\Concerns\RecordsMedicalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Relevé de constantes vitales (§20).
 * Correspondance FHIR : Observation (§44).
 *
 * Historisation (§40) : chaque relevé est une ligne distincte. Une valeur
 * n'est jamais écrasée, ce qui permet les courbes d'évolution et conserve
 * la date et l'auteur de chaque mesure.
 */
class VitalSign extends Model
{
    protected $table = 'dme_vital_signs';

    use HasFactory;
    use RecordsMedicalActivity;

    protected $fillable = [
        'patient_id', 'consultation_id', 'hospitalization_id', 'measured_at',
        'temperature', 'systolic', 'diastolic', 'heart_rate', 'respiratory_rate',
        'oxygen_saturation', 'weight', 'height', 'bmi', 'glycemia',
        'pain_scale', 'comment', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'measured_at' => 'datetime',
            'temperature' => 'float',
            'weight' => 'float',
            'height' => 'float',
            'bmi' => 'float',
            'glycemia' => 'float',
        ];
    }

    protected static function booted(): void
    {
        // L'IMC est toujours dérivé du poids et de la taille du même relevé.
        static::saving(function (VitalSign $vitalSign): void {
            $vitalSign->bmi = $vitalSign->computeBmi();
        });
    }

    public function computeBmi(): ?float
    {
        if (! $this->weight || ! $this->height || $this->height <= 0) {
            return null;
        }

        $heightInMeters = $this->height / 100;

        return round($this->weight / ($heightInMeters ** 2), 2);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(Dme::userModel(), 'recorded_by');
    }

    public function bloodPressure(): ?string
    {
        return $this->systolic && $this->diastolic
            ? $this->systolic.'/'.$this->diastolic
            : null;
    }

    /**
     * Indique si une constante sort de l'intervalle de référence configuré.
     * Purement informatif : l'application n'établit aucun diagnostic.
     */
    public function isOutOfRange(string $key): bool
    {
        $range = config("dme.vitals.{$key}");
        $value = $this->{$key};

        if ($range === null || $value === null) {
            return false;
        }

        return $value < $range['min'] || $value > $range['max'];
    }

    public function auditLabel(): string
    {
        return 'Constantes du '.$this->measured_at?->format('d/m/Y H:i');
    }
}
