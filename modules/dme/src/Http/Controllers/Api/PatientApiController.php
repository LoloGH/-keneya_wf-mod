<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Controllers\Api;

use Keneya\Dme\Http\Controllers\Controller;
use Keneya\Dme\Http\Requests\StorePatientRequest;
use Keneya\Dme\Http\Resources\PatientResource;
use Keneya\Dme\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * API REST : patients (§43).
 *
 * Les autorisations passent par les mêmes policies que l'interface web :
 * l'API n'est pas une porte dérobée, un jeton n'accorde jamais plus que
 * le rôle de son porteur.
 */
class PatientApiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Patient::class);

        $patients = Patient::query()
            ->with('attendingDoctor:id,name,first_name,last_name,title')
            ->search($request->string('q')->toString())
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(min($request->integer('per_page', 25), 100));

        return PatientResource::collection($patients);
    }

    public function store(StorePatientRequest $request): JsonResponse
    {
        $patient = Patient::create($request->safe()->except([
            'emergency_contact', 'known_allergies', 'chronic_conditions',
        ]) + ['created_by' => $request->user()->id]);

        return PatientResource::make($patient)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Patient $patient): PatientResource
    {
        $this->authorize('view', $patient);

        return PatientResource::make(
            $patient->load(['attendingDoctor', 'allergies', 'identifiers'])
        );
    }

    public function update(StorePatientRequest $request, Patient $patient): PatientResource
    {
        $patient->update($request->safe()->except([
            'emergency_contact', 'known_allergies', 'chronic_conditions',
        ]));

        return PatientResource::make($patient->fresh());
    }
}
