<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Correspondance FHIR visée : DocumentReference (§44).
 *
 * Le chemin de stockage n'est jamais exposé (§42) : seule l'URL de la
 * route de téléchargement contrôlée est renvoyée.
 *
 * @mixin \Keneya\Dme\Models\MedicalDocument
 */
class MedicalDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'identifier' => $this->document_number,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'version' => $this->version,
            'contentType' => $this->mime_type,
            'size' => $this->size_bytes,
            'date' => $this->created_at?->toIso8601String(),
            'author' => $this->whenLoaded('uploader', fn () => $this->uploader?->displayName()),
            'downloadUrl' => route('dme.documents.download', $this->resource),
        ];
    }
}
