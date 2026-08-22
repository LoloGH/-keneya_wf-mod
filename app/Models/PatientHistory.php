<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal append-only du parcours d'un patient : jamais modifie ni supprime
 * apres insertion (voir PatientHistoryRecorder, seul point d'ecriture).
 */
class PatientHistory extends Model
{
    use HasFactory;

    public const TYPE_REGISTRATION = 'registration';

    public const TYPE_CONSULTATION = 'consultation';

    public const TYPE_REFERRAL_SENT = 'referral_sent';

    public const TYPE_REFERRAL_RESULT = 'referral_result';

    /**
     * @var array<string, string>
     */
    public const TYPE_LABELS = [
        self::TYPE_REGISTRATION => 'Enregistrement',
        self::TYPE_CONSULTATION => 'Consultation',
        self::TYPE_REFERRAL_SENT => 'Renvoi envoye',
        self::TYPE_REFERRAL_RESULT => 'Resultat de renvoi',
    ];

    protected $table = 'patient_history';

    protected $fillable = [
        'patient_id',
        'type',
        'service_id',
        'doctor_id',
        'referral_id',
        'description',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }
}
