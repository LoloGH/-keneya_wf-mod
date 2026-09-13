<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

/**
 * Journal d'audit append-only (§30).
 *
 * Aucune entrée ne peut être modifiée ni supprimée par un utilisateur :
 * les événements `updating` et `deleting` sont bloqués au niveau du modèle
 * en plus de l'absence de route et de policy correspondantes. Seule une
 * purge d'exploitation (hors application) pourrait intervenir.
 */
class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'activity_log';

    protected $fillable = [
        'log_name', 'description', 'subject_type', 'subject_id', 'event',
        'causer_type', 'causer_id', 'properties', 'batch_uuid',
        'causer_role', 'patient_id', 'action', 'outcome',
        'ip_address', 'user_agent', 'route',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \RuntimeException(
            "Le journal d'audit est en écriture seule : une entrée ne peut pas être modifiée."
        ));

        static::deleting(fn () => throw new \RuntimeException(
            "Le journal d'audit est en écriture seule : une entrée ne peut pas être supprimée."
        ));
    }

    /**
     * Écrit une entrée d'audit à partir du contexte de requête courant.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function record(
        string $action,
        ?Model $subject = null,
        array $properties = [],
        ?int $patientId = null,
        string $outcome = 'allowed',
        ?string $description = null,
    ): self {
        $user = Auth::user();
        $request = request();

        if ($subject !== null && $patientId === null && method_exists($subject, 'auditPatientId')) {
            $patientId = $subject->auditPatientId();
        }

        $label = $subject !== null && method_exists($subject, 'auditLabel')
            ? $subject->auditLabel()
            : ($subject !== null ? class_basename($subject).' #'.$subject->getKey() : null);

        return self::create([
            'log_name' => 'medical',
            'description' => $description ?? ($label ?? $action),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'event' => $action,
            'action' => $action,
            'causer_type' => $user?->getMorphClass(),
            'causer_id' => $user?->getKey(),
            'causer_role' => $user?->getRoleNames()->first(),
            'patient_id' => $patientId,
            'outcome' => $outcome,
            'properties' => $properties ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'route' => $request?->path(),
        ]);
    }

    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Libellé français de l'action, pour l'affichage.
     */
    public function actionLabel(): string
    {
        return match ($this->action) {
            'viewed' => 'A consulté',
            'created' => 'A créé',
            'updated' => 'A modifié',
            'deleted' => 'A supprimé',
            'downloaded' => 'A téléchargé',
            'printed' => 'A imprimé',
            'login' => 'S\'est connecté',
            'logout' => 'S\'est déconnecté',
            'denied' => 'Accès refusé',
            default => (string) $this->action,
        };
    }
}
