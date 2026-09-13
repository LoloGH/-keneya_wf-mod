<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Keneya\Dme\Dme;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Message SMS et son statut d'acheminement (§35).
 *
 * Le lien vers le patient et vers l'objet métier est volontairement
 * faible (colonnes non contraintes) : le service SMS ne dépend d'aucun
 * modèle du DME.
 */
class SmsMessage extends Model
{
    protected $table = 'dme_sms_messages';

    use HasFactory;

    protected $fillable = [
        'reference', 'recipient', 'body', 'sender', 'sms_template_id',
        'patient_id', 'context_type', 'context_id', 'status', 'attempts',
        'scheduled_for', 'accepted_at', 'sent_at', 'delivered_at', 'failed_at',
        'status_checked_at', 'error_message',
        'gateway', 'gateway_message_id', 'gateway_response', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'accepted_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'status_checked_at' => 'datetime',
            'gateway_response' => 'array',
            'attempts' => 'integer',
        ];
    }

    /**
     * Cycle de vie d'un message, du plus précoce au plus avancé.
     *
     * « Accepté » et « Envoyé » sont volontairement distincts : une
     * passerelle comme SMSGate accuse d'abord réception du message sans
     * l'avoir émis. L'application ne doit jamais présenter un message
     * comme envoyé avant confirmation du fournisseur.
     *
     * @var array<string, string>
     */
    public const STATUSES = [
        'pending' => 'En attente',
        'queued' => 'Dans la file',
        'accepted' => 'Accepté par la passerelle',
        'sent' => 'Envoyé',
        'delivered' => 'Remis',
        'failed' => 'Échec',
        'cancelled' => 'Annulé',
    ];

    /** États au-delà desquels le suivi d'acheminement n'a plus lieu d'être. */
    public const FINAL_STATUSES = ['delivered', 'failed', 'cancelled'];

    /** États pour lesquels la passerelle peut encore faire évoluer le message. */
    public const IN_TRANSIT_STATUSES = ['accepted', 'sent'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(SmsTemplate::class, 'sms_template_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Dme::userModel(), 'created_by');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Messages encore susceptibles d'évoluer côté passerelle.
     */
    public function scopeInTransit(Builder $query): Builder
    {
        return $query->whereIn('status', self::IN_TRANSIT_STATUSES)
            ->whereNotNull('gateway_message_id');
    }

    /**
     * Un message dont l'acheminement est arrêté, quel qu'en soit l'issue.
     */
    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL_STATUSES, true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Un message en échec peut être rejoué tant que le quota d'essais reste ouvert. */
    public function isRetryable(): bool
    {
        return $this->status === 'failed'
            && $this->attempts < (int) config('dme.sms.retry.max_attempts');
    }
}
