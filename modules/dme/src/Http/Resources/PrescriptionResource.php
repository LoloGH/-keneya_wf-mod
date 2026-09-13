<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Correspondance FHIR visée : MedicationRequest (§44).
 *
 * @mixin \Keneya\Dme\Models\Prescription
 */
class PrescriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'identifier' => $this->prescription_number,
            'status' => $this->status,
            'authoredOn' => $this->issued_on?->toDateString(),
            'validUntil' => $this->valid_until?->toDateString(),
            'requester' => $this->whenLoaded('doctor', fn () => [
                'id' => $this->doctor?->id,
                'display' => $this->doctor?->displayName(),
            ]),
            'encounterId' => $this->consultation_id,
            'medications' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'sequence' => $item->position,
                'medication' => $item->medication_name,
                'dosage' => $item->dosage,
                'form' => $item->form,
                'route' => $item->route,
                'frequency' => $item->frequency,
                'duration' => $item->duration,
                'quantity' => $item->quantity,
                'instructions' => $item->instructions,
            ])->all()),
            // Le contrôle d'allergie est exposé : un client tiers doit
            // pouvoir afficher le même avertissement que l'interface (§22).
            'allergyWarnings' => $this->allergy_warnings,
        ];
    }
}
