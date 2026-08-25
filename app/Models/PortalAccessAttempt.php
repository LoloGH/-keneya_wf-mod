<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Compteur d'echecs de saisie du code d'acces au portail patient.
 *
 * Le lien ne perime jamais : c'est ce verrouillage temporaire, et non une
 * expiration, qui empeche d'essayer les 10 000 codes possibles. Il est
 * volontairement temporaire — un patient qui se trompe deux fois ne doit pas
 * se retrouver bloque a vie.
 */
class PortalAccessAttempt extends Model
{
    /** Nombre d'echecs tolere avant verrouillage. */
    public const MAX_FAILURES = 5;

    /** Duree du verrouillage, en minutes. */
    public const LOCK_MINUTES = 15;

    protected $fillable = ['patient_id', 'failures', 'locked_until'];

    protected function casts(): array
    {
        return [
            'failures' => 'integer',
            'locked_until' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function minutesRemaining(): int
    {
        return $this->isLocked() ? max(1, (int) ceil(now()->diffInSeconds($this->locked_until) / 60)) : 0;
    }
}
