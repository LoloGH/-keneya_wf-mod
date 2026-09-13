<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Keneya\Dme\Dme;
use Keneya\Dme\Models\Concerns\HasBusinessIdentifier;
use Keneya\Dme\Models\Concerns\RecordsMedicalActivity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Keneya\Dme\Database\Factories\PrescriptionFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ordonnance (§22). Correspondance FHIR : MedicationRequest (§44).
 *
 * Contrôle d'allergie : les correspondances détectées sont enregistrées
 * dans `allergy_warnings` au moment de la validation. L'application
 * avertit et trace, mais ne retire jamais une ligne de prescription :
 * la décision appartient au prescripteur.
 */
class Prescription extends Model
{
    protected $table = 'dme_prescriptions';

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
        return PrescriptionFactory::new();
    }

    protected $fillable = [
        'prescription_number', 'source_system', 'source_id',
        'patient_id', 'consultation_id', 'doctor_id',
        'issued_on', 'valid_until', 'status', 'instructions',
        'allergy_warnings', 'allergy_warning_acknowledged',
        'allergy_warning_justification', 'validated_by', 'validated_at',
        'dispensed_by', 'dispensed_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'valid_until' => 'date',
            'validated_at' => 'datetime',
            'dispensed_at' => 'datetime',
            'allergy_warnings' => 'array',
            'allergy_warning_acknowledged' => 'boolean',
        ];
    }

    /** @var array<string, string> */
    public const STATUSES = [
        'draft' => 'Brouillon',
        'validated' => 'Validée',
        'dispensed' => 'Délivrée',
        'cancelled' => 'Annulée',
    ];

    public function identifierPrefixKey(): string
    {
        return 'prescription';
    }

    public function identifierColumn(): string
    {
        return 'prescription_number';
    }

    public function auditLabel(): string
    {
        return 'Ordonnance '.$this->prescription_number;
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Dme::userModel(), 'doctor_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(Dme::userModel(), 'validated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class)->orderBy('position');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function hasAllergyWarnings(): bool
    {
        return filled($this->allergy_warnings);
    }
}
