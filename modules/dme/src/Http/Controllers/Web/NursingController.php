<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Controllers\Web;

use Keneya\Dme\Http\Controllers\Controller;
use Keneya\Dme\Models\NursingNote;
use Keneya\Dme\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Soins infirmiers (§26) : soins, administrations, observations,
 * incidents et transmissions, horodatés et signés.
 */
class NursingController extends Controller
{
    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $this->authorize('create', NursingNote::class);

        $data = $request->validate([
            'hospitalization_id' => ['nullable', 'exists:dme_hospitalizations,id'],
            'type' => ['required', Rule::in(array_keys(NursingNote::TYPES))],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'title' => ['required', 'string', 'max:200'],
            'content' => ['nullable', 'string', 'max:5000'],
            'medication_name' => ['nullable', 'required_if:type,medication_administration', 'string', 'max:200'],
            'medication_dose' => ['nullable', 'string', 'max:100'],
            'medication_route' => ['nullable', 'string', 'max:50'],
            'severity' => ['required', Rule::in(['info', 'warning', 'critical'])],
        ], [
            'medication_name.required_if' => 'Précisez le médicament administré.',
        ], [
            'type' => 'type de soin',
            'occurred_at' => 'date et heure',
            'title' => 'intitulé',
            'severity' => 'criticité',
        ]);

        $patient->nursingNotes()->create($data + ['nurse_id' => $request->user()->id]);

        return back()->with('success', 'Soin enregistré dans le dossier.');
    }
}
