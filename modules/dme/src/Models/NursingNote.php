<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Keneya\Dme\Dme;
use Keneya\Dme\Models\Concerns\RecordsMedicalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Soin infirmier, administration ou transmission (§26). */
class NursingNote extends Model
{
    protected $table = 'dme_nursing_notes';

    use HasFactory;
    use RecordsMedicalActivity;

    protected $fillable = [
        'patient_id', 'hospitalization_id', 'type', 'occurred_at', 'title',
        'content', 'medication_name', 'medication_dose', 'medication_route',
        'severity', 'nurse_id',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    /** @var array<string, string> */
    public const TYPES = [
        'care' => 'Soin',
        'medication_administration' => 'Médicament administré',
        'observation' => 'Observation',
        'incident' => 'Incident',
        'handover' => 'Transmission',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function hospitalization(): BelongsTo
    {
        return $this->belongsTo(Hospitalization::class);
    }

    public function nurse(): BelongsTo
    {
        return $this->belongsTo(Dme::userModel(), 'nurse_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function auditLabel(): string
    {
        return 'Soin - '.$this->title;
    }
}
