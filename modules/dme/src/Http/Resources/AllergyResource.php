<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Correspondance FHIR visée : AllergyIntolerance (§44).
 *
 * @mixin \Keneya\Dme\Models\Allergy
 */
class AllergyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => ['text' => $this->allergen],
            'category' => $this->allergen_type,
            'criticality' => $this->severity,
            'clinicalStatus' => $this->status,
            'reaction' => $this->reaction,
            'onsetDate' => $this->observed_on?->toDateString(),
        ];
    }
}
