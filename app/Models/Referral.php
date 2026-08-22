<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_DONE = 'done';

    /**
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'En attente',
        self::STATUS_DONE => 'Termine',
    ];

    protected $fillable = [
        'patient_id',
        'from_service_id',
        'to_service_id',
        'from_doctor_id',
        'completed_by_doctor_id',
        'instructions',
        'status',
        'result_text',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function fromService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'from_service_id');
    }

    public function toService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'to_service_id');
    }

    public function fromDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'from_doctor_id');
    }

    public function completedByDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'completed_by_doctor_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
