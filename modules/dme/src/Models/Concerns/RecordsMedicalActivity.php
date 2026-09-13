<?php

declare(strict_types=1);

namespace Keneya\Dme\Models\Concerns;

use Keneya\Dme\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Journalise dans le registre d'audit la création, la modification et la
 * suppression des enregistrements médicaux (§30).
 *
 * Le modèle utilisateur peut redéfinir `auditPatientId()` lorsque le
 * patient concerné n'est pas porté par une colonne `patient_id`.
 */
trait RecordsMedicalActivity
{
    protected static function bootRecordsMedicalActivity(): void
    {
        static::created(fn (Model $model) => AuditLog::record('created', $model));
        static::updated(fn (Model $model) => AuditLog::record('updated', $model, $model->getChanges()));
        static::deleted(fn (Model $model) => AuditLog::record('deleted', $model));
    }

    /**
     * Patient concerné par l'enregistrement, pour le filtrage de l'audit.
     */
    public function auditPatientId(): ?int
    {
        return isset($this->attributes['patient_id'])
            ? (int) $this->attributes['patient_id']
            : null;
    }

    /**
     * Libellé lisible de l'enregistrement dans le journal d'audit.
     */
    public function auditLabel(): string
    {
        return class_basename($this).' #'.$this->getKey();
    }
}
