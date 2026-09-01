<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Trace d'un SMS, de sa mise en file a son sort final (v3.2.8).
 *
 * Une ligne est creee des la mise en file (`queued`), avant tout appel reseau :
 * meme un message que la passerelle n'acceptera jamais laisse une trace, ce qui
 * est precisement ce qui manquait au journal texte qu'elle remplace.
 */
class SmsMessage extends Model
{
    use HasFactory;

    /** En file, pas encore soumis a la passerelle. */
    public const STATUS_QUEUED = 'queued';

    /** Accepte par la passerelle. Etat final en l'absence d'accuse de remise. */
    public const STATUS_SENT = 'sent';

    /** Remise confirmee sur le telephone du destinataire. */
    public const STATUS_DELIVERED = 'delivered';

    /** Definitivement echoue, apres epuisement des tentatives. */
    public const STATUS_FAILED = 'failed';

    /**
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        self::STATUS_QUEUED => 'En file',
        self::STATUS_SENT => 'Envoye',
        self::STATUS_DELIVERED => 'Remis',
        self::STATUS_FAILED => 'Echoue',
    ];

    protected $fillable = [
        'to',
        'body',
        'related_type',
        'related_id',
        'status',
        'provider_message_id',
        'failure_reason',
        'attempts',
        'sent_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /** L'objet metier a l'origine du message : patient, visiteur, renvoi... */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    /** @param  Builder<self>  $query */
    public function scopeFailed(Builder $query): void
    {
        $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Les echecs des dernieres 24 h — le chiffre affiche en tete de la section
     * « SMS » de l'administration, pour qu'une passerelle en panne se voie sans
     * avoir a ouvrir la liste.
     */
    public static function recentFailureCount(): int
    {
        return static::query()->failed()->where('created_at', '>=', now()->subDay())->count();
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public static function auditLabel(): string
    {
        return 'SMS';
    }
}
