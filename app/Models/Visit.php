<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un passage — un episode de soins.
 *
 * `patients` porte l'identite permanente (un patient_code a vie) ; `visits`
 * porte le passage. Un patient qui revient six mois plus tard pour une autre
 * pathologie ouvre une nouvelle visite sous le meme identifiant, sans jamais
 * ecraser l'episode precedent.
 *
 * Une visite se deplace de service en service au fil des renvois : c'est le
 * meme episode qui traverse l'hopital, et il se cloture une seule fois, a la
 * fin de la prise en charge.
 */
class Visit extends Model
{
    use HasFactory, RecordsActivity;

    public const STATUS_WAITING = 'waiting';

    public const STATUS_CALLED = 'called';

    public const STATUS_CLOSED = 'closed';

    /**
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        self::STATUS_WAITING => 'En attente',
        self::STATUS_CALLED => 'Appele',
        self::STATUS_CLOSED => 'Cloture',
    ];

    protected $fillable = [
        'patient_id',
        'service_id',
        'pending_next_service_id',
        'token',
        'status',
        'pathology_id',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'token' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * La file du jour d'un service.
     *
     * Une visite rejoint une file au moment ou son `service_id` et son `token`
     * sont ecrits — a l'ouverture, puis a chaque renvoi. `updated_at` marque
     * donc son entree dans la file courante.
     *
     * Les files repartent a 1 chaque matin : ce scope est le seul endroit qui
     * definit « la file d'aujourd'hui », et il est partage par l'attribution
     * des tickets, la file du medecin et l'ecran de salle d'attente.
     */
    public function scopeInTodaysQueue(Builder $query, int $serviceId): Builder
    {
        return $query->where('service_id', $serviceId)
            ->whereDate('updated_at', today());
    }

    /**
     * Les visites encore actives : ni cloturees, ni sorties de la file du jour.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_CLOSED);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * La pathologie notee a la conclusion de consultation (v3.2.9, point 1).
     * Toujours facultative : elle sert a regrouper, jamais a conditionner.
     */
    public function pathology(): BelongsTo
    {
        return $this->belongsTo(Pathology::class);
    }

    /**
     * Le service ou la visite ira une fois le paiement confirme.
     */
    public function pendingNextService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'pending_next_service_id');
    }

    public function awaitsPayment(): bool
    {
        return $this->pending_next_service_id !== null;
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(PatientHistory::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Ordonnances anterieures a la v3.3.1, restees dans la table de WorkFlow.
     *
     * Plus rien ne s'y ecrit depuis la fusion des deux ordonnances : une
     * ordonnance nouvelle part dans le dossier medical, ou elle est rattachee
     * au patient et non au passage. La relation subsiste pour la reprise et
     * pour la suppression d'un dossier.
     */
    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * Un renvoi encore sans resultat empeche la cloture du dossier : on ne
     * ferme pas un episode en attente d'un examen.
     */
    public function hasPendingReferral(): bool
    {
        return $this->referrals()->where('status', Referral::STATUS_PENDING)->exists();
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public static function auditLabel(): string
    {
        return 'Passage';
    }
}
