<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    use HasFactory, RecordsActivity;

    /** Taille maximale acceptee, en kilo-octets (10 Mo). */
    public const MAX_SIZE_KB = 10240;

    /**
     * Extensions et types MIME autorises. Les deux sont verifies cote serveur :
     * un nom de fichier ne prouve rien.
     *
     * @var array<int, string>
     */
    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    /**
     * @var array<int, string>
     */
    public const ALLOWED_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    protected $fillable = [
        'patient_id',
        'visit_id',
        'patient_history_id',
        'referral_id',
        'original_name',
        'path',
        'mime_type',
        'size',
        'uploaded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function historyEntry(): BelongsTo
    {
        return $this->belongsTo(PatientHistory::class, 'patient_history_id');
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /**
     * Les types dont l'apercu integre est sur.
     *
     * Liste blanche, et non « tout sauf » : une piece jointe arrive par
     * televersement, et servir un HTML ou un SVG en ligne reviendrait a
     * executer du script de l'utilisateur sur le domaine de l'application.
     *
     * @var array<int, string>
     */
    public const PREVIEWABLE_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /**
     * Cette piece se regarde-t-elle dans le navigateur, sans telechargement ?
     *
     * Obliger a telecharger pour lire laisse une copie du dossier sur chaque
     * poste qui l'a consultee — dans un hopital, sur des postes partages.
     */
    public function isPreviewable(): bool
    {
        return in_array((string) $this->mime_type, self::PREVIEWABLE_MIMES, true);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function humanSize(): string
    {
        if ($this->size < 1024) {
            return $this->size.' o';
        }

        if ($this->size < 1024 * 1024) {
            return round($this->size / 1024).' Ko';
        }

        return round($this->size / (1024 * 1024), 1).' Mo';
    }

    public static function auditLabel(): string
    {
        return 'Piece jointe';
    }
}
