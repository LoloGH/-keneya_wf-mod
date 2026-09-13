<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Correspondance FHIR visée : Condition (§44).
 *
 * @mixin \Keneya\Dme\Models\Diagnosis
 */
class DiagnosisResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => [
                'coding' => array_filter([
                    'system' => $this->code_system,
                    'code' => $this->code,
                ]),
                'text' => $this->label,
            ],
            'category' => $this->type,
            'clinicalStatus' => $this->status,
            'recordedDate' => $this->diagnosed_on?->toDateString(),
            'note' => $this->comment,
        ];
    }
}
