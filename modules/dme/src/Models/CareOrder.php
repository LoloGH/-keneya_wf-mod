<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Keneya\Dme\Contracts\DmeUser;
use Keneya\Dme\Dme;
use Keneya\Dme\Models\Concerns\HasBusinessIdentifier;
use Keneya\Dme\Models\Concerns\RecordsMedicalActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Soin programmé : la prescription de soin, distincte du soin réalisé.
 *
 * NursingNote consigne ce qui a été fait ; CareOrder porte ce qui est
 * demandé. Les deux se rejoignent à la réalisation, qui bascule le statut
 * et signe l'exécutant.
 */
class CareOrder extends Model
{
    protected $table = 'dme_care_orders';

    use HasBusinessIdentifier;
    use HasFactory;
    use RecordsMedicalActivity;

    protected $fillable = [
        'reference', 'patient_id', 'hospitalization_id', 'prescriber_id',
        'service_id', 'assigned_nurse_id', 'title', 'instructions', 'frequency',
        'priority', 'starts_at', 'ends_at', 'status', 'completed_at',
        'completed_by_id', 'outcome',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @var array<string, string> */
    public const STATUSES = [
        'planned' => 'Programmé',
        'completed' => 'Réalisé',
        'refused' => 'Non réalisé',
        'cancelled' => 'Annulé',
    ];

    /** @var array<string, string> */
    public const PRIORITIES = [
        'routine' => 'Courant',
        'urgent' => 'Urgent',
    ];

    public function identifierPrefixKey(): string
    {
        return 'care_order';
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function hospitalization(): BelongsTo
    {
        return $this->belongsTo(Hospitalization::class);
    }

    public function prescriber(): BelongsTo
    {
        return $this->belongsTo(Dme::userModel(), 'prescriber_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function assignedNurse(): BelongsTo
    {
        return $this->belongsTo(Dme::userModel(), 'assigned_nurse_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(Dme::userModel(), 'completed_by_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }

    public function isOpen(): bool
    {
        return $this->status === 'planned';
    }

    /** Un soin sans destinataire nommé revient à la garde du service. */
    public function isUnassigned(): bool
    {
        return $this->assigned_nurse_id === null;
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->starts_at->isPast();
    }

    /**
     * Portée de visibilité d'un soin programmé.
     *
     * Un soin confié à quelqu'un ne concerne que cette personne et le
     * prescripteur. Un soin ouvert revient au personnel de garde du
     * service prescripteur, pas à tout l'établissement : c'est le service
     * qui répond du patient.
     *
     * Le contrôle reste doublé côté policy : cette portée filtre la liste,
     * la policy tranche l'accès à une ligne précise.
     */
    public function scopeVisibleTo(Builder $query, DmeUser $user): Builder
    {
        // L'administration voit tout : c'est le rôle qui répond de l'établissement.
        if ($user->can('users.manage')) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user): void {
            $q->where('prescriber_id', $user->id)
                ->orWhere('assigned_nurse_id', $user->id);

            // Soin ouvert : réservé au personnel de garde du service prescripteur.
            if ($user->isOnDuty() && $user->service_id !== null) {
                $q->orWhere(function (Builder $open) use ($user): void {
                    $open->whereNull('assigned_nurse_id')
                        ->where('service_id', $user->service_id);
                });
            }
        });
    }

    public function auditLabel(): string
    {
        return 'Soin programmé - '.$this->title;
    }

    public function auditPatientId(): ?int
    {
        return $this->patient_id;
    }
}
