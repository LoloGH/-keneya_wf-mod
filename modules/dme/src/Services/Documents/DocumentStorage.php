<?php

declare(strict_types=1);

namespace Keneya\Dme\Services\Documents;

use Keneya\Dme\Models\MedicalDocument;
use Keneya\Dme\Models\Patient;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stockage sécurisé des documents médicaux (§28, §42).
 *
 * Règles appliquées :
 *   - les fichiers vont sur un disque privé, jamais dans public/ ;
 *   - le nom de fichier est régénéré (aucune donnée patient dans le
 *     chemin, aucun risque de traversée de répertoire) ;
 *   - le chemin physique n'est jamais exposé, c'est la route contrôlée
 *     documents.download qui sert le fichier après vérification de la
 *     policy ;
 *   - un document remplacé n'est pas écrasé : une nouvelle version est
 *     créée et chaînée à la précédente (§40).
 */
class DocumentStorage
{
    public function disk(): Filesystem
    {
        return Storage::disk((string) config('dme.documents.disk', 'local'));
    }

    /**
     * Enregistre un fichier téléversé dans le dossier d'un patient.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function storeUploaded(Patient $patient, UploadedFile $file, array $attributes = []): MedicalDocument
    {
        $path = $this->buildPath($patient, $file->getClientOriginalExtension());

        $this->disk()->put($path, $file->get(), ['visibility' => 'private']);

        return MedicalDocument::create(array_merge([
            'patient_id' => $patient->getKey(),
            'title' => $attributes['title'] ?? $file->getClientOriginalName(),
            'type' => $attributes['type'] ?? 'imported',
            'disk' => (string) config('dme.documents.disk', 'local'),
            'storage_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()) ?: null,
            'status' => 'final',
            'is_generated' => false,
            'uploaded_by' => Auth::id(),
        ], $attributes));
    }

    /**
     * Enregistre un contenu produit par l'application (PDF généré).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function storeGenerated(
        Patient $patient,
        string $contents,
        string $title,
        string $type,
        array $attributes = [],
    ): MedicalDocument {
        $path = $this->buildPath($patient, 'pdf');

        $this->disk()->put($path, $contents, ['visibility' => 'private']);

        return MedicalDocument::create(array_merge([
            'patient_id' => $patient->getKey(),
            'title' => $title,
            'type' => $type,
            'disk' => (string) config('dme.documents.disk', 'local'),
            'storage_path' => $path,
            'original_name' => Str::slug($title).'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($contents),
            'checksum' => hash('sha256', $contents),
            'status' => 'final',
            'is_generated' => true,
            'uploaded_by' => Auth::id(),
        ], $attributes));
    }

    /**
     * Lit le contenu binaire d'un document.
     *
     * L'appelant DOIT avoir vérifié la policy au préalable : cette méthode
     * ne fait aucun contrôle d'accès, elle lit le disque.
     */
    public function read(MedicalDocument $document): string
    {
        $disk = Storage::disk($document->disk);

        if (! $disk->exists($document->storage_path)) {
            throw new RuntimeException('Le fichier associé à ce document est introuvable.');
        }

        return (string) $disk->get($document->storage_path);
    }

    /**
     * Construit un chemin privé non devinable.
     *
     * Le dossier est cloisonné par patient pour faciliter l'exploitation,
     * mais le nom du fichier est aléatoire : connaître l'identifiant d'un
     * patient ne permet pas de deviner l'URL d'un document.
     */
    private function buildPath(Patient $patient, ?string $extension): string
    {
        $extension = preg_replace('/[^a-zA-Z0-9]/', '', (string) $extension) ?: 'bin';

        return sprintf(
            '%s/%d/%s.%s',
            trim((string) config('dme.documents.directory', 'medical-documents'), '/'),
            $patient->getKey(),
            Str::uuid()->toString(),
            mb_strtolower($extension),
        );
    }
}
