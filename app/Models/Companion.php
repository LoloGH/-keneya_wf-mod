<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Accompagnateur d'un patient : information non medicale, sans ticket ni file
 * d'attente propre.
 */
class Companion extends Model
{
    use HasFactory, RecordsActivity;

    protected $fillable = ['patient_id', 'name', 'phone', 'relation'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public static function auditLabel(): string
    {
        return 'Accompagnateur';
    }
}
