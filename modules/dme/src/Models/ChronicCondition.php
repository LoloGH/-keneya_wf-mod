<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Keneya\Dme\Models\Concerns\RecordsMedicalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pathologie chronique. Correspondance FHIR : Condition (§44). */
class ChronicCondition extends Model
{
    protected $table = 'dme_chronic_conditions';

    use HasFactory;
    use RecordsMedicalActivity;

    protected $fillable = [
        'patient_id', 'label', 'code', 'code_system', 'diagnosed_on',
        'status', 'comment', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['diagnosed_on' => 'date'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function auditLabel(): string
    {
        return 'Pathologie chronique - '.$this->label;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'active' => 'Active',
            'controlled' => 'Contrôlée',
            'resolved' => 'Résolue',
            default => $this->status,
        };
    }
}
