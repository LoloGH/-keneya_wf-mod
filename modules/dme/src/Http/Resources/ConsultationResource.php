<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Correspondance FHIR visée : Encounter (§44).
 *
 * @mixin \Keneya\Dme\Models\Consultation
 */
class ConsultationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'identifier' => $this->consultation_number,
            'status' => $this->status,
            'class' => $this->type,
            'subject' => ['patientId' => $this->patient_id],
            'period' => [
                'start' => $this->started_at?->toIso8601String(),
                'end' => $this->ended_at?->toIso8601String(),
            ],
            'participant' => $this->whenLoaded('doctor', fn () => [
                'id' => $this->doctor?->id,
                'display' => $this->doctor?->displayName(),
            ]),
            'serviceProvider' => $this->whenLoaded('service', fn () => $this->service?->name),
            'reasonCode' => $this->reason,
            'historyOfIllness' => $this->history_of_illness,
            'diagnosis' => DiagnosisResource::collection($this->whenLoaded('diagnoses')),
            'vitalSigns' => VitalSignResource::collection($this->whenLoaded('vitalSigns')),
        ];
    }
}
