<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Correspondance FHIR visée : Observation (§44).
 *
 * @mixin \Keneya\Dme\Models\VitalSign
 */
class VitalSignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'effectiveDateTime' => $this->measured_at?->toIso8601String(),
            'components' => array_values(array_filter([
                $this->temperature ? ['code' => 'body-temperature', 'value' => $this->temperature, 'unit' => 'Cel'] : null,
                $this->systolic ? ['code' => 'systolic-blood-pressure', 'value' => $this->systolic, 'unit' => 'mm[Hg]'] : null,
                $this->diastolic ? ['code' => 'diastolic-blood-pressure', 'value' => $this->diastolic, 'unit' => 'mm[Hg]'] : null,
                $this->heart_rate ? ['code' => 'heart-rate', 'value' => $this->heart_rate, 'unit' => '/min'] : null,
                $this->respiratory_rate ? ['code' => 'respiratory-rate', 'value' => $this->respiratory_rate, 'unit' => '/min'] : null,
                $this->oxygen_saturation ? ['code' => 'oxygen-saturation', 'value' => $this->oxygen_saturation, 'unit' => '%'] : null,
                $this->weight ? ['code' => 'body-weight', 'value' => $this->weight, 'unit' => 'kg'] : null,
                $this->height ? ['code' => 'body-height', 'value' => $this->height, 'unit' => 'cm'] : null,
                $this->bmi ? ['code' => 'bmi', 'value' => $this->bmi, 'unit' => 'kg/m2'] : null,
                $this->glycemia ? ['code' => 'glucose', 'value' => $this->glycemia, 'unit' => 'g/L'] : null,
            ])),
        ];
    }
}
