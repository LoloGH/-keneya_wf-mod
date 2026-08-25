<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasFactory, RecordsActivity;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_CHECKED_IN = 'checked_in';

    public const STATUS_NO_SHOW = 'no_show';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        self::STATUS_SCHEDULED => 'Prevu',
        self::STATUS_CHECKED_IN => 'Patient arrive',
        self::STATUS_NO_SHOW => 'Non presente',
        self::STATUS_CANCELLED => 'Annule',
    ];

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'service_id',
        'scheduled_at',
        'status',
        'visit_id',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public static function auditLabel(): string
    {
        return 'Rendez-vous';
    }
}
