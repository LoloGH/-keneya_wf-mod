<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Keneya\Dme\Dme;
use Keneya\Dme\Models\Concerns\RecordsMedicalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Diagnostic (§21). Correspondance FHIR : Condition (§44).
 *
 * `code` / `code_system` sont prévus pour la CIM-10 : la structure est en
 * place, le référentiel complet n'est pas embarqué en phase 1.
 */
class Diagnosis extends Model
{
    use HasFactory;
    use RecordsMedicalActivity;

    protected $table = 'dme_diagnoses';

    protected $fillable = [
        'patient_id', 'consultation_id', 'doctor_id', 'label', 'code',
        'code_system', 'type', 'status', 'diagnosed_on', 'comment',
    ];

    protected function casts(): array
    {
        return ['diagnosed_on' => 'date'];
    }

    /** @var array<string, string> */
    public const STATUSES = [
        'suspected' => 'Suspecté',
        'confirmed' => 'Confirmé',
        'chronic' => 'Chronique',
        'resolved' => 'Résolu',
    ];

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

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function auditLabel(): string
    {
        return 'Diagnostic '.$this->label;
    }
}
