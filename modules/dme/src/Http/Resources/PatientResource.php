<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation API d'un patient (§43).
 *
 * Les noms de champs et la structure suivent volontairement la ressource
 * FHIR « Patient » (§44) : `identifier`, `name`, `gender`, `birthDate`,
 * `telecom`, `address`. La première version ne produit pas du FHIR
 * conforme, mais la correspondance est immédiate et sans perte.
 *
 * @mixin \Keneya\Dme\Models\Patient
 */
class PatientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'identifier' => [
                ['system' => 'keneya-dme/patient-number', 'value' => $this->patient_number],
                ...$this->whenLoaded('identifiers', fn () => $this->identifiers
                    ->map(fn ($identifier) => [
                        'system' => $identifier->system,
                        'value' => $identifier->value,
                    ])->all(), []),
            ],
            'name' => [
                'family' => $this->last_name,
                'given' => $this->first_name,
                'text' => $this->fullName(),
            ],
            'gender' => $this->sex,
            'birthDate' => $this->birth_date?->toDateString(),
            'age' => $this->age(),
            'deceased' => $this->status === 'deceased',
            'telecom' => array_values(array_filter([
                $this->phone ? ['system' => 'phone', 'value' => $this->phone, 'use' => 'mobile'] : null,
                $this->phone_secondary ? ['system' => 'phone', 'value' => $this->phone_secondary, 'use' => 'home'] : null,
                $this->email ? ['system' => 'email', 'value' => $this->email] : null,
            ])),
            'address' => array_filter([
                'line' => $this->address,
                'city' => $this->city,
                'country' => $this->country,
            ]),
            'bloodGroup' => $this->blood_group,
            'status' => $this->status,
            'generalPractitioner' => $this->whenLoaded('attendingDoctor', fn () => [
                'id' => $this->attendingDoctor?->id,
                'display' => $this->attendingDoctor?->displayName(),
            ]),
            'allergies' => AllergyResource::collection($this->whenLoaded('allergies')),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
