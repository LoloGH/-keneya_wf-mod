@extends('dme::layouts.app')

@section('title', 'Ordonnance '.$prescription->prescription_number)

@section('content')
    <x-dme::page-header :title="'Ordonnance '.$prescription->prescription_number"
                   :subtitle="$prescription->issued_on->translatedFormat('l d F Y')"
                   :breadcrumbs="[
                       'Ordonnances' => route('dme.prescriptions.index'),
                       $prescription->patient->fullName() => route('dme.patients.show', $prescription->patient),
                       $prescription->prescription_number => null,
                   ]">
        <x-slot:actions>
            <a href="{{ route('dme.prescriptions.pdf', $prescription) }}" target="_blank" rel="noopener" class="k-btn-secondary">
                <x-dme::icon name="print" class="h-4 w-4"/> Imprimer / PDF
            </a>
            @can('documents.upload')
                <a href="{{ route('dme.prescriptions.pdf', [$prescription, 'archive' => 1]) }}" target="_blank" rel="noopener"
                   class="k-btn-secondary">Archiver au dossier</a>
            @endcan
            @can('dispense', $prescription)
                <form action="{{ route('dme.prescriptions.dispense', $prescription) }}" method="POST">
                    @csrf
                    <button type="submit" class="k-btn-primary">Marquer comme délivrée</button>
                </form>
            @endcan
        </x-slot:actions>
    </x-dme::page-header>

    {{-- Avertissement allergie (§22) : visible, explicite, jamais bloquant --}}
    @if ($prescription->hasAllergyWarnings())
        <div class="k-alert-critical mb-4" role="alert">
            <x-dme::icon name="alert" class="mt-0.5 h-5 w-5 shrink-0 text-red-600"/>
            <div>
                <p class="text-sm font-semibold text-red-800">
                    Contrôle d'allergie - {{ count($prescription->allergy_warnings) }} correspondance(s)
                </p>
                <ul class="mt-1 space-y-0.5 text-sm text-red-700">
                    @foreach ($prescription->allergy_warnings as $warning)
                        <li>
                            <span class="font-medium">{{ $warning['medication'] }}</span>
                            ↔ allergie à <span class="font-medium">{{ $warning['allergen'] }}</span>
                            ({{ $warning['severity_label'] }}) - {{ $warning['reason'] }}
                        </li>
                    @endforeach
                </ul>
                @if ($prescription->allergy_warning_acknowledged)
                    <p class="mt-2 text-xs text-red-700">
                        Alerte prise en compte par
                        {{ $prescription->validator?->displayName() ?? 'le prescripteur' }}
                        le {{ $prescription->validated_at?->translatedFormat('d M Y à H:i') }}.
                        @if ($prescription->allergy_warning_justification)
                            Justification : « {{ $prescription->allergy_warning_justification }} »
                        @endif
                    </p>
                @endif
            </div>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <section class="k-card">
                <div class="k-card-header">
                    <h2 class="k-card-title">Médicaments prescrits</h2>
                    <x-dme::status-badge :status="$prescription->status" :label="$prescription->statusLabel()"/>
                </div>
                <div class="k-card-body">
                    <ol class="space-y-3">
                        @foreach ($prescription->items as $item)
                            <li class="rounded-lg border border-ink-200 p-3">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <p class="font-semibold text-ink-900">
                                        {{ $item->position }}. {{ $item->medication_name }}
                                        @if ($item->dosage)
                                            <span class="font-normal text-ink-600">{{ $item->dosage }}</span>
                                        @endif
                                    </p>
                                    @if ($item->form)
                                        <span class="k-badge-neutral">{{ $item->form }}</span>
                                    @endif
                                </div>
                                <dl class="mt-1.5 grid grid-cols-2 gap-x-4 gap-y-1 text-sm sm:grid-cols-4">
                                    @foreach ([
                                        'Voie' => $item->route,
                                        'Fréquence' => $item->frequency,
                                        'Durée' => $item->duration,
                                        'Quantité' => $item->quantity,
                                    ] as $label => $value)
                                        <div>
                                            <dt class="text-xs text-ink-400">{{ $label }}</dt>
                                            <dd class="text-ink-800">{{ $value ?: '-' }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                                @if ($item->instructions)
                                    <p class="mt-1.5 text-sm text-ink-600">{{ $item->instructions }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>

                    @if ($prescription->instructions)
                        <div class="mt-4 rounded-lg bg-ink-50 p-3">
                            <h3 class="text-xs font-semibold tracking-wide text-ink-500 uppercase">Instructions générales</h3>
                            <p class="mt-1 text-sm text-ink-800">{{ $prescription->instructions }}</p>
                        </div>
                    @endif
                </div>
            </section>

            {{-- Validation (§22) : confirmation explicite en cas d'alerte --}}
            @can('validate', $prescription)
                <section class="k-card">
                    <div class="k-card-header"><h2 class="k-card-title">Validation de l'ordonnance</h2></div>
                    <form action="{{ route('dme.prescriptions.validate', $prescription) }}" method="POST" class="k-card-body space-y-3">
                        @csrf
                        @if ($prescription->hasAllergyWarnings())
                            <label class="flex items-start gap-2 rounded-lg border border-red-300 bg-red-50 p-3 text-sm">
                                <input type="checkbox" name="acknowledge_allergy" value="1" required
                                       class="mt-0.5 h-4 w-4 rounded border-red-400 text-red-600">
                                <span class="text-red-800">
                                    Je confirme avoir pris connaissance de l'alerte allergie ci-dessus et
                                    maintenir cette prescription en connaissance de cause.
                                </span>
                            </label>
                            <x-dme::field-error name="acknowledge_allergy"/>
                            <div>
                                <label for="allergy_justification" class="k-label">Justification clinique (recommandée)</label>
                                <textarea id="allergy_justification" name="allergy_justification" rows="2" maxlength="1000"
                                          class="k-textarea"
                                          placeholder="Absence d'alternative, allergie invalidée cliniquement, désensibilisation..."></textarea>
                            </div>
                        @else
                            <p class="text-sm text-ink-600">
                                Aucune correspondance n'a été trouvée entre les médicaments prescrits et les
                                allergies documentées du patient.
                            </p>
                        @endif
                        <button type="submit" class="k-btn-primary">Valider l'ordonnance</button>
                    </form>
                </section>
            @endcan
        </div>

        <div class="space-y-4">
            <section class="k-card">
                <div class="k-card-header"><h2 class="k-card-title">Patient</h2></div>
                <div class="k-card-body">
                    <x-dme::patient-header :patient="$prescription->patient" compact/>
                </div>
            </section>

            <section class="k-card">
                <div class="k-card-header"><h2 class="k-card-title">Suivi</h2></div>
                <dl class="k-card-body space-y-2.5 text-sm">
                    <div>
                        <dt class="text-xs text-ink-500">Prescripteur</dt>
                        <dd class="font-medium text-ink-900">{{ $prescription->doctor?->displayName() ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-ink-500">Validité</dt>
                        <dd class="text-ink-800">
                            {{ $prescription->valid_until?->translatedFormat('d F Y') ?? 'Non précisée' }}
                        </dd>
                    </div>
                    @if ($prescription->validated_at)
                        <div>
                            <dt class="text-xs text-ink-500">Validée le</dt>
                            <dd class="text-ink-800">{{ $prescription->validated_at->translatedFormat('d M Y à H:i') }}</dd>
                        </div>
                    @endif
                    @if ($prescription->dispensed_at)
                        <div>
                            <dt class="text-xs text-ink-500">Délivrée le</dt>
                            <dd class="text-ink-800">{{ $prescription->dispensed_at->translatedFormat('d M Y à H:i') }}</dd>
                        </div>
                    @endif
                    @if ($prescription->consultation)
                        <div>
                            <dt class="text-xs text-ink-500">Consultation liée</dt>
                            <dd>
                                <a href="{{ route('dme.consultations.show', $prescription->consultation) }}"
                                   class="font-mono text-xs text-clinic-700 hover:underline">
                                    {{ $prescription->consultation->consultation_number }}
                                </a>
                            </dd>
                        </div>
                    @endif
                </dl>
            </section>

            @if ($prescription->patient->allergies->isNotEmpty())
                <section class="k-card">
                    <div class="k-card-header"><h2 class="k-card-title">Allergies connues</h2></div>
                    <ul class="k-card-body space-y-1.5 text-sm">
                        @foreach ($prescription->patient->allergies as $allergy)
                            <li class="flex items-center justify-between gap-2">
                                <span class="text-ink-800">{{ $allergy->allergen }}</span>
                                <x-dme::status-badge :status="$allergy->severity" :label="$allergy->severityLabel()"/>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>
@endsection
