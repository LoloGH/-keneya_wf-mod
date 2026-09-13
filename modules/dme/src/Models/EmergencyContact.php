<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Personne à prévenir en cas d'urgence (§12). */
class EmergencyContact extends Model
{
    protected $table = 'dme_emergency_contacts';

    use HasFactory;

    protected $fillable = [
        'patient_id', 'name', 'relationship', 'phone',
        'phone_secondary', 'address', 'is_primary',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
