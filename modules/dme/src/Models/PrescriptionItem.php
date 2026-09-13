<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ligne d'une ordonnance (§22). */
class PrescriptionItem extends Model
{
    protected $table = 'dme_prescription_items';

    use HasFactory;

    protected $fillable = [
        'prescription_id', 'position', 'medication_name', 'dosage', 'form',
        'route', 'frequency', 'duration', 'quantity', 'instructions',
        'is_substitutable',
    ];

    protected function casts(): array
    {
        return ['is_substitutable' => 'boolean'];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    /** Posologie condensée pour l'affichage et le PDF. */
    public function posology(): string
    {
        return collect([$this->dosage, $this->frequency, $this->duration])
            ->filter()
            ->implode(' · ');
    }
}
