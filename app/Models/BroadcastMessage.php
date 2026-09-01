<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Une diffusion de SMS depuis l'administration (v3.2.9, point 1).
 */
class BroadcastMessage extends Model
{
    use HasFactory, RecordsActivity;

    /** Un membre du personnel, nommement. */
    public const TARGET_STAFF = 'staff';

    /** Un patient, nommement. */
    public const TARGET_SINGLE_PATIENT = 'single_patient';

    /** Les patients passes par un service, eventuellement filtres par pathologie. */
    public const TARGET_PATIENT_GROUP = 'patient_group';

    /** Tous les patients de l'etablissement. */
    public const TARGET_ALL_PATIENTS = 'all_patients';

    /**
     * @var array<string, string>
     */
    public const TARGET_LABELS = [
        self::TARGET_STAFF => 'Un membre du personnel',
        self::TARGET_SINGLE_PATIENT => 'Un patient',
        self::TARGET_PATIENT_GROUP => 'Un groupe de patients',
        self::TARGET_ALL_PATIENTS => 'Tous les patients',
    ];

    protected $fillable = [
        'sent_by_user_id',
        'content',
        'target_type',
        'target_filters',
        'recipient_count',
    ];

    protected function casts(): array
    {
        return [
            'target_filters' => 'array',
            'recipient_count' => 'integer',
        ];
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    /**
     * Le detail des envois : un `sms_messages` par destinataire, avec son
     * statut. C'est ici que se lit ce qu'est devenue la diffusion.
     *
     * @return MorphMany<SmsMessage, $this>
     */
    public function smsMessages(): MorphMany
    {
        return $this->morphMany(SmsMessage::class, 'related');
    }

    public function targetLabel(): string
    {
        return self::TARGET_LABELS[$this->target_type] ?? $this->target_type;
    }

    public static function auditLabel(): string
    {
        return 'Diffusion SMS';
    }
}
