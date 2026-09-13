<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Correspondance FHIR visée : ServiceRequest / DiagnosticReport (§44).
 *
 * @mixin \Keneya\Dme\Models\LabOrder
 */
class LabOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'identifier' => $this->order_number,
            'status' => $this->status,
            'priority' => $this->priority,
            'authoredOn' => $this->requested_at?->toIso8601String(),
            'completedAt' => $this->completed_at?->toIso8601String(),
            'reasonCode' => $this->indication,
            'requester' => $this->whenLoaded('doctor', fn () => [
                'id' => $this->doctor?->id,
                'display' => $this->doctor?->displayName(),
            ]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'exam' => $item->exam_name,
                'category' => $item->category,
                'status' => $item->status,
                'results' => $item->relationLoaded('results')
                    ? $item->results->map(fn ($result) => [
                        'parameter' => $result->parameter,
                        'value' => $result->value,
                        'unit' => $result->unit,
                        'referenceRange' => $result->reference_range,
                        'interpretation' => $result->flag,
                        'effectiveDateTime' => $result->measured_at?->toIso8601String(),
                    ])->all()
                    : [],
            ])->all()),
        ];
    }
}
