<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Keneya\Dme\Dme;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Créneau habituel d'un compte pour un jour de la semaine donné (§60).
 *
 * @property int $weekday 1 = lundi ... 7 = dimanche (Carbon::dayOfWeekIso)
 * @property string|null $starts_at Heure de début, format H:i:s
 * @property string|null $ends_at Heure de fin, format H:i:s
 */
class UserWeeklySchedule extends Model
{
    protected $table = 'dme_user_weekly_schedules';

    protected $fillable = ['weekday', 'starts_at', 'ends_at'];

    public const WEEKDAYS = [
        1 => 'Lundi',
        2 => 'Mardi',
        3 => 'Mercredi',
        4 => 'Jeudi',
        5 => 'Vendredi',
        6 => 'Samedi',
        7 => 'Dimanche',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(Dme::userModel());
    }

    public function isRestDay(): bool
    {
        return $this->starts_at === null || $this->ends_at === null;
    }
}
