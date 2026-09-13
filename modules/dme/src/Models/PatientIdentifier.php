<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Identifiant externe d'un patient (registre national, assurance, et à
 * terme Keneya Workflow). Correspondance FHIR : Patient.identifier (§44).
 *
 * C'est le point de raccordement prévu pour la phase 2 (§62).
 */
class PatientIdentifier extends Model
{
    protected $table = 'dme_patient_identifiers';

    use HasFactory;

    protected $fillable = ['patient_id', 'system', 'value', 'label', 'is_primary'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
