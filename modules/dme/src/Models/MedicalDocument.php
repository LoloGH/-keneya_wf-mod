<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Keneya\Dme\Dme;
use Keneya\Dme\Models\Concerns\HasBusinessIdentifier;
use Keneya\Dme\Models\Concerns\RecordsMedicalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Document médical (§28). Correspondance FHIR : DocumentReference (§44).
 *
 * Sécurité (§42) : `storage_path` pointe vers un disque privé. Ce chemin
 * n'est jamais rendu dans une vue ni exposé par l'API ; l'accès au fichier
 * passe uniquement par la route contrôlée documents.download, protégée par
 * MedicalDocumentPolicy et tracée dans le journal d'audit.
 */
class MedicalDocument extends Model
{
    protected $table = 'dme_medical_documents';

    use HasBusinessIdentifier;
    use HasFactory;
    use RecordsMedicalActivity;
    use SoftDeletes;

    protected $fillable = [
        'document_number', 'patient_id', 'title', 'type', 'description',
        'disk', 'storage_path', 'original_name', 'mime_type', 'size_bytes',
        'checksum', 'version', 'replaces_document_id', 'status',
        'is_generated', 'source_type', 'source_id', 'uploaded_by',
    ];

    /**
     * Le chemin de stockage est masqué par défaut : il ne doit jamais
     * fuiter dans une réponse JSON ou une vue.
     *
     * @var list<string>
     */
    protected $hidden = ['storage_path', 'disk'];

    protected function casts(): array
    {
        return [
            'is_generated' => 'boolean',
            'size_bytes' => 'integer',
            'version' => 'integer',
        ];
    }

    /** @var array<string, string> */
    public const TYPES = [
        'prescription' => 'Ordonnance',
        'lab_result' => 'Résultat de laboratoire',
        'imaging_report' => 'Compte rendu d\'imagerie',
        'discharge_summary' => 'Compte rendu d\'hospitalisation',
        'certificate' => 'Certificat',
        'medical_letter' => 'Lettre médicale',
        'consultation_report' => 'Compte rendu de consultation',
        'imported' => 'Document importé',
        'other' => 'Autre',
    ];

    public function identifierPrefixKey(): string
    {
        return 'document';
    }

    public function identifierColumn(): string
    {
        return 'document_number';
    }

    public function auditLabel(): string
    {
        return 'Document '.$this->document_number.' - '.$this->title;
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Dme::userModel(), 'uploaded_by');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_document_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function humanSize(): string
    {
        $bytes = $this->size_bytes;

        if ($bytes < 1024) {
            return $bytes.' o';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' Ko';
        }

        return round($bytes / 1048576, 1).' Mo';
    }

    /**
     * Les types dont l'aperçu intégré est sûr.
     *
     * Volontairement une liste blanche, et non « tout sauf » : un document
     * médical arrive par téléversement, et servir un HTML ou un SVG en ligne
     * reviendrait à exécuter du script de l'utilisateur sur le domaine du
     * dossier. Le PDF et les images matricielles suffisent à ce que le
     * praticien a besoin de regarder : un compte rendu, une échographie.
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

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/')
            && in_array($this->mime_type, self::PREVIEWABLE_MIMES, true);
    }

    /** Ce document se regarde-t-il dans le navigateur, sans téléchargement ? */
    public function isPreviewable(): bool
    {
        return in_array((string) $this->mime_type, self::PREVIEWABLE_MIMES, true);
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }
}
