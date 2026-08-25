<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /** Le prescripteur a pris connaissance du resultat et ferme la boucle. */
    public const TYPE_REFERRAL_CLOSED = 'referral_closed';

    /** L'episode de soins est termine dans le service courant. */
    public const TYPE_DOSSIER_CLOSED = 'dossier_closed';

    public const TYPE_PRESCRIPTION = 'prescription';

    /** Conclusion redigee par le medecin en fin de prise en charge. */
    public const TYPE_CONSULTATION_CONCLUSION = 'consultation_conclusion';

    /** Paiement encaisse a la caisse, le patient est oriente vers son service. */
    public const TYPE_PAYMENT_CONFIRMED = 'payment_confirmed';

    /**
     * @var array<string, string>
     */
    public const TYPE_LABELS = [
        self::TYPE_REGISTRATION => 'Enregistrement',
        self::TYPE_CONSULTATION => 'Consultation',
        self::TYPE_REFERRAL_SENT => 'Renvoi envoye',
        self::TYPE_REFERRAL_RESULT => 'Resultat de renvoi',
        self::TYPE_REFERRAL_CLOSED => 'Renvoi cloture',
        self::TYPE_DOSSIER_CLOSED => 'Dossier cloture',
        self::TYPE_PRESCRIPTION => 'Ordonnance',
        self::TYPE_CONSULTATION_CONCLUSION => 'Conclusion de consultation',
        self::TYPE_PAYMENT_CONFIRMED => 'Paiement confirme',
    ];

    protected $table = 'patient_history';

    protected $fillable = [
        'patient_id',
        'visit_id',
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

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
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

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'patient_history_id');
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }
}
