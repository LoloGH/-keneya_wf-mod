<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Keneya\Dme\Dme;
use Keneya\Dme\Models\Concerns\HasBusinessIdentifier;
use Keneya\Dme\Models\Concerns\RecordsMedicalActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Keneya\Dme\Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Patient : racine du dossier médical électronique.
 *
 * Correspondance FHIR visée : Patient (§44).
 *
 * Phase 2 (§62) : le raccordement au patient unique de Keneya Workflow se
 * fera par `patient_identifiers`, sans toucher aux données cliniques qui
 * pointent toutes vers `patients.id`.
 */
class Patient extends Model
{
    protected $table = 'dme_patients';

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
        return PatientFactory::new();
    }

    protected $fillable = [
        'patient_number', 'last_name', 'first_name', 'sex', 'birth_date',
        'birth_date_estimated', 'birth_place', 'nationality', 'marital_status',
        'occupation', 'id_card_number', 'photo_path', 'phone', 'phone_secondary', 'email',
        'address', 'city', 'country', 'blood_group', 'attending_doctor_id',
        'status', 'deceased_at', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'deceased_at' => 'date',
            'birth_date_estimated' => 'boolean',
        ];
    }

    public function identifierPrefixKey(): string
    {
        return 'patient';
    }

    public function identifierColumn(): string
    {
        return 'patient_number';
    }

    public function auditPatientId(): ?int
    {
        return $this->getKey();
    }

    public function auditLabel(): string
    {
        return $this->patient_number.' - '.$this->fullName();
    }

    // -----------------------------------------------------------------
    // Relations (§39)
    // -----------------------------------------------------------------

    public function attendingDoctor(): BelongsTo
    {
        return $this->belongsTo(Dme::userModel(), 'attending_doctor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Dme::userModel(), 'created_by');
    }

    /**
     * L'identifiant principal de ce patient dans le système qui l'a adressé.
     *
     * Un dossier créé depuis une application hôte y porte déjà un numéro, et
     * c'est celui-là que le patient connaît, que l'accueil appelle et que le
     * personnel lit sur son ticket. Les documents imprimés le rappellent à
     * côté du numéro du dossier médical : sans lui, la feuille que le patient
     * emporte ne se rattache plus à rien de ce qu'il a en main.
     */
    public function externalIdentifier(): ?string
    {
        $identifiant = $this->identifiers
            ->firstWhere('is_primary', true) ?? $this->identifiers->first();

        return $identifiant?->value;
    }

    public function identifiers(): HasMany
    {
        return $this->hasMany(PatientIdentifier::class);
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class);
    }

    public function medicalHistories(): HasMany
    {
        return $this->hasMany(MedicalHistory::class);
    }

    public function allergies(): HasMany
    {
        return $this->hasMany(Allergy::class);
    }

    public function chronicConditions(): HasMany
    {
        return $this->hasMany(ChronicCondition::class);
    }

    public function medications(): HasMany
    {
        return $this->hasMany(Medication::class);
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class)->orderByDesc('started_at');
    }

    public function vitalSigns(): HasMany
    {
        return $this->hasMany(VitalSign::class)->orderByDesc('measured_at');
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class)->orderByDesc('issued_on');
    }

    public function labOrders(): HasMany
    {
        return $this->hasMany(LabOrder::class)->orderByDesc('requested_at');
    }

    public function labResults(): HasMany
    {
        return $this->hasMany(LabResult::class);
    }

    public function imagingOrders(): HasMany
    {
        return $this->hasMany(ImagingOrder::class)->orderByDesc('requested_at');
    }

    public function imagingReports(): HasMany
    {
        return $this->hasMany(ImagingReport::class);
    }

    public function hospitalizations(): HasMany
    {
        return $this->hasMany(Hospitalization::class)->orderByDesc('admitted_at');
    }

    public function careOrders(): HasMany
    {
        return $this->hasMany(CareOrder::class);
    }

    public function nursingNotes(): HasMany
    {
        return $this->hasMany(NursingNote::class)->orderByDesc('occurred_at');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MedicalDocument::class)->orderByDesc('created_at');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'patient_id')->orderByDesc('created_at');
    }

    public function smsMessages(): HasMany
    {
        return $this->hasMany(SmsMessage::class, 'patient_id')->orderByDesc('created_at');
    }

    // -----------------------------------------------------------------
    // Attributs dérivés
    // -----------------------------------------------------------------

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function age(): ?int
    {
        return $this->birth_date === null
            ? null
            : (int) $this->birth_date->diffInYears(Carbon::today());
    }

    public function ageLabel(): string
    {
        $age = $this->age();

        if ($age === null) {
            return 'Âge inconnu';
        }

        if ($age < 1 && $this->birth_date !== null) {
            return ((int) $this->birth_date->diffInMonths(Carbon::today())).' mois';
        }

        return $age.' ans';
    }

    public function sexLabel(): string
    {
        return match ($this->sex) {
            'male' => 'Homme',
            'female' => 'Femme',
            'other' => 'Autre',
            default => 'Non précisé',
        };
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1));
    }

    /**
     * Allergies sévères actives : affichées en alerte permanente du DME (§13).
     *
     * @return Collection<int, Allergy>
     */
    public function criticalAllergies(): Collection
    {
        return $this->allergies
            ->where('status', 'active')
            ->whereIn('severity', ['severe', 'moderate'])
            ->sortByDesc(fn (Allergy $allergy) => $allergy->severity === 'severe' ? 1 : 0)
            ->values();
    }

    /**
     * Pathologies chroniques actives : également affichées en alerte (§13).
     *
     * @return Collection<int, ChronicCondition>
     */
    public function activeConditions(): Collection
    {
        return $this->chronicConditions->whereIn('status', ['active', 'controlled'])->values();
    }

    public function lastConsultation(): ?Consultation
    {
        return $this->consultations()->latest('started_at')->first();
    }

    public function nextAppointment(): ?Appointment
    {
        return $this->appointments()
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->where('scheduled_for', '>=', now())
            ->orderBy('scheduled_for')
            ->first();
    }

    public function latestVitalSign(): ?VitalSign
    {
        return $this->vitalSigns()->first();
    }

    public function isHospitalized(): bool
    {
        return $this->hospitalizations()->where('status', 'admitted')->exists();
    }

    // -----------------------------------------------------------------
    // Recherche (§11)
    // -----------------------------------------------------------------

    /**
     * Recherche par nom, prénom, numéro de dossier, téléphone ou date de
     * naissance. La requête reste paramétrée : aucune interpolation de
     * chaîne n'est effectuée (§41).
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $inner) use ($like, $term): void {
            $inner->where('last_name', 'like', $like)
                ->orWhere('first_name', 'like', $like)
                ->orWhere('patient_number', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('phone_secondary', 'like', $like);

            // Date de naissance : accepte aaaa-mm-jj et jj/mm/aaaa
            foreach ([$term, self::normalizeFrenchDate($term)] as $candidate) {
                if ($candidate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidate)) {
                    $inner->orWhereDate('birth_date', $candidate);
                }
            }
        });
    }

    private static function normalizeFrenchDate(string $term): ?string
    {
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $term, $matches) === 1) {
            return $matches[3].'-'.$matches[2].'-'.$matches[1];
        }

        return null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
