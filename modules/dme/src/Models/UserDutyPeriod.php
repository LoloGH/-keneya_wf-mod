<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Keneya\Dme\Dme;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Garde planifiée à l'avance pour un compte (§60).
 */
class UserDutyPeriod extends Model
{
    protected $table = 'dme_user_duty_periods';

    protected $fillable = ['starts_at', 'ends_at', 'notes', 'created_by_id'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Dme::userModel());
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Dme::userModel(), 'created_by_id');
    }

    /**
     * Périodes qui n'ont pas encore débuté : celles que l'administrateur
     * peut encore librement modifier ou retirer sans réécrire l'historique.
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>', now());
    }

    public function scopeCurrentlyActive(Builder $query): Builder
    {
        return $query->where('starts_at', '<=', now())->where('ends_at', '>', now());
    }
}
