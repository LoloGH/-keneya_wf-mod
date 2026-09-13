<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Examen clinique par appareil, rattaché à une consultation (§19). */
class ClinicalNote extends Model
{
    protected $table = 'dme_clinical_notes';

    use HasFactory;

    protected $fillable = ['consultation_id', 'system', 'content', 'is_abnormal'];

    protected function casts(): array
    {
        return ['is_abnormal' => 'boolean'];
    }

    /** @var array<string, string> */
    public const SYSTEMS = [
        'general' => 'État général',
        'cardiovascular' => 'Cardiovasculaire',
        'respiratory' => 'Respiratoire',
        'abdominal' => 'Abdominal',
        'neurological' => 'Neurologique',
        'ent' => 'ORL',
        'dermatological' => 'Dermatologique',
        'other' => 'Autres',
    ];

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function systemLabel(): string
    {
        return self::SYSTEMS[$this->system] ?? $this->system;
    }
}
