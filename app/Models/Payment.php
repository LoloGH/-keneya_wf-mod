<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Encaissement. Montants en FCFA, sans decimales.
 */
class Payment extends Model
{
    use HasFactory;

    public const TYPE_TICKET = 'ticket';

    public const TYPE_SERVICE = 'service';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    /**
     * @var array<string, string>
     */
    public const TYPE_LABELS = [
        self::TYPE_TICKET => 'Ticket de consultation',
        self::TYPE_SERVICE => 'Acte / examen',
    ];

    /**
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'A encaisser',
        self::STATUS_PAID => 'Encaisse',
    ];

    protected $fillable = [
        'patient_id',
        'visit_id',
        'type',
        'service_id',
        'amount',
        'status',
        'recorded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * Montant formate a la maniere malienne : « 2 500 FCFA ».
     */
    public function formattedAmount(): string
    {
        return number_format((float) $this->amount, 0, ',', ' ').' FCFA';
    }
}
