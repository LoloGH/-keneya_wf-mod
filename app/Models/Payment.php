<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Encaissement. Montants en FCFA, sans decimales.
 */
class Payment extends Model
{
    use HasFactory, RecordsActivity;

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
        'billable_item_id',
        'amount',
        'catalog_price',
        'status',
        'recorded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'catalog_price' => 'integer',
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

    /** L'acte facture, quand il vient du catalogue (v3.2.8, point 3). */
    public function billableItem(): BelongsTo
    {
        return $this->belongsTo(BillableItem::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    /**
     * Ce que le patient a paye, nomme : l'acte du catalogue s'il y en a un,
     * sinon le type d'encaissement — les encaissements anterieurs au catalogue
     * n'ont rien de plus precis a montrer.
     */
    public function subjectLabel(): string
    {
        return $this->billableItem?->name ?? $this->typeLabel();
    }

    /**
     * Le montant encaisse s'ecarte-t-il du tarif du catalogue ? Une derogation
     * doit rester visible : c'est l'exception, pas le fonctionnement normal.
     */
    public function isOverridden(): bool
    {
        return $this->catalog_price !== null && $this->catalog_price !== $this->amount;
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

    public static function auditLabel(): string
    {
        return 'Encaissement';
    }
}
