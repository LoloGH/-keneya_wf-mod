<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Keneya\Dme\Dme;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Événement de la timeline d'un séjour hospitalier (§25). */
class HospitalizationEvent extends Model
{
    protected $table = 'dme_hospitalization_events';

    use HasFactory;

    protected $fillable = [
        'hospitalization_id', 'type', 'occurred_at', 'title', 'content',
        'source_type', 'source_id', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    /** @var array<string, string> */
    public const TYPES = [
        'admission' => 'Admission',
        'observation' => 'Observation',
        'care' => 'Soins',
        'exam' => 'Examens',
        'treatment' => 'Traitement',
        'evolution' => 'Évolution',
        'transfer' => 'Transfert',
        'discharge' => 'Sortie',
    ];

    public function hospitalization(): BelongsTo
    {
        return $this->belongsTo(Hospitalization::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(Dme::userModel(), 'recorded_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
