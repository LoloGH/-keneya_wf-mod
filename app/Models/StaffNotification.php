<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une notification adressee a une personne (v3.2.3, point 2).
 *
 * Volontairement pauvre : un type, un titre deja redige en francais, un lien.
 * Rien n'y est calcule a la lecture, parce que la cloche interroge cette table
 * toutes les dix secondes pour chaque personne connectee.
 */
class StaffNotification extends Model
{
    use HasFactory;

    /** Un patient ou un visiteur vient d'entrer dans une file. */
    public const TYPE_NEW_QUEUE_ENTRY = 'new_queue_entry';

    /** Le resultat d'un renvoi est revenu au prescripteur. */
    public const TYPE_REFERRAL_RESULT = 'referral_result';

    /** Des soins viennent d'etre prescrits sur le service. */
    public const TYPE_CARE_TASK_ASSIGNED = 'care_task_assigned';

    /** Un rendez-vous approche. */
    public const TYPE_APPOINTMENT_REMINDER = 'appointment_reminder';

    /** De nouvelles lignes de planning concernent ce compte. */
    public const TYPE_SCHEDULE_PUBLISHED = 'schedule_published';

    /**
     * @var array<string, string>
     */
    public const TYPE_LABELS = [
        self::TYPE_NEW_QUEUE_ENTRY => 'File d\'attente',
        self::TYPE_REFERRAL_RESULT => 'Resultat de renvoi',
        self::TYPE_CARE_TASK_ASSIGNED => 'Soins programmes',
        self::TYPE_APPOINTMENT_REMINDER => 'Rendez-vous',
        self::TYPE_SCHEDULE_PUBLISHED => 'Planning',
    ];

    protected $fillable = ['user_id', 'type', 'title', 'link', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public static function auditLabel(): string
    {
        return 'Notification';
    }
}
