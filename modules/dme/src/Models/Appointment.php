<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Keneya\Dme\Dme;
use Keneya\Dme\Models\Concerns\HasBusinessIdentifier;
use Keneya\Dme\Models\Concerns\RecordsMedicalActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Keneya\Dme\Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Rendez-vous (§27). Correspondance FHIR : Appointment (§44). */
class Appointment extends Model
{
    protected $table = 'dme_appointments';

    use HasBusinessIdentifier;
    use HasFactory;
    use RecordsMedicalActivity;
    use SoftDeletes;

    /**
     * Le module étant un package, la fabrique ne se devine pas par
     * convention : elle est désignée explicitement.
     */
    protected static function newFactory(): Factory
    {
        return AppointmentFactory::new();
    }

    protected $fillable = [
        'appointment_number', 'patient_id', 'doctor_id', 'service_id',
        'scheduled_for', 'duration_minutes', 'reason', 'status', 'notes',
        'reminder_enabled', 'reminder_sent_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'reminder_enabled' => 'boolean',
        ];
    }

    /** @var array<string, string> */
    public const STATUSES = [
        'scheduled' => 'Programmé',
        'confirmed' => 'Confirmé',
        'completed' => 'Terminé',
        'cancelled' => 'Annulé',
        'no_show' => 'Absent',
    ];

    public function identifierPrefixKey(): string
    {
        return 'appointment';
    }

    public function identifierColumn(): string
    {
        return 'appointment_number';
    }

    public function auditLabel(): string
    {
        return 'Rendez-vous '.$this->appointment_number;
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Dme::userModel(), 'doctor_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereIn('status', ['scheduled', 'confirmed'])
            ->where('scheduled_for', '>=', now())
            ->orderBy('scheduled_for');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function endsAt(): \Illuminate\Support\Carbon
    {
        return $this->scheduled_for->copy()->addMinutes($this->duration_minutes);
    }
}
