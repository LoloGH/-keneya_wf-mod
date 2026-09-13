@extends('dme::layouts.app')

@section('title', 'Consultation '.$consultation->consultation_number)

@section('content')
    <x-dme::page-header :title="'Consultation '.$consultation->consultation_number"
                   :subtitle="$consultation->started_at->translatedFormat('l d F Y à H:i')"
                   :breadcrumbs="[
                       'Patients' => route('dme.patients.index'),
                       $consultation->patient->fullName() => route('dme.patients.show', $consultation->patient),
                       $consultation->consultation_number => null,
                   ]">
        <x-slot:actions>
            <a href="{{ route('dme.consultations.report-pdf', $consultation) }}" target="_blank" rel="noopener"
               class="k-btn-secondary">
                <x-dme::icon name="print" class="h-4 w-4"/> Compte rendu PDF
            </a>
            @can('update', $consultation)
                <a href="{{ route('dme.consultations.edit', $consultation) }}" class="k-btn-secondary">Modifier</a>
            @endcan
            @can('complete', $consultation)
                <form action="{{ route('dme.consultations.complete', $consultation) }}" method="POST"
                      onsubmit="return confirm('Terminer cette consultation ? Elle ne sera plus modifiable.');">
                    @csrf
                    <button type="submit" class="k-btn-primary">Terminer la consultation</button>
                </form>
            @endcan
            @can('prescriptions.create')
                <a href="{{ route('dme.prescriptions.create', ['patient' => $consultation->patient, 'consultation' => $consultation->id]) }}"
                   class="k-btn-primary">
                    <x-dme::icon name="plus" class="h-4 w-4"/> Ordonnance
                </a>
            @endcan
        </x-slot:actions>
    </x-dme::page-header>

    <section class="k-card mb-4">
        <div class="p-4 sm:p-5">
            <x-dme::patient-header :patient="$consultation->patient" compact/>
            @if ($consultation->patient->criticalAllergies()->isNotEmpty() || $consultation->patient->activeConditions()->isNotEmpty())
                <div class="mt-4"><x-dme::medical-alerts :patient="$consultation->patient"/></div>
            @endif
        </div>
    </section>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">

            <section class="k-card">
                <div class="k-card-header">
                    <h2 class="k-card-title">Motif et anamnèse</h2>
                    <x-dme::status-badge :status="$consultation->status" :label="$consultation->statusLabel()"/>
                </div>
                <div class="k-card-body space-y-3">
                    <div>
                        <h3 class="text-xs font-semibold tracking-wide text-ink-500 uppercase">Motif</h3>
                        <p class="mt-1 text-sm text-ink-800">{{ $consultation->reason ?: 'Non renseigné' }}</p>
                    </div>
                    <div>
                        <h3 class="text-xs font-semibold tracking-wide text-ink-500 uppercase">Histoire de la maladie</h3>
                        <p class="mt-1 text-sm leading-relaxed whitespace-pre-line text-ink-800">
                            {{ $consultation->history_of_illness ?: 'Non renseignée' }}
                        </p>
                    </div>
                </div>
            </section>

            @if ($consultation->clinicalNotes->isNotEmpty())
                <section class="k-card">
                    <div class="k-card-header"><h2 class="k-card-title">Examen clinique</h2></div>
                    <div class="k-card-body grid gap-3 sm:grid-cols-2">
                        @foreach ($consultation->clinicalNotes as $note)
                            <div class="rounded-lg border p-3 {{ $note->is_abnormal ? 'border-amber-300 bg-amber-50' : 'border-ink-200' }}">
                                <h3 class="text-xs font-semibold tracking-wide text-ink-500 uppercase">
                                    {{ $note->systemLabel() }}
                                    @if ($note->is_abnormal)
                                        <span class="ml-1 text-amber-700">· anomalie</span>
                                    @endif
                                </h3>
                                <p class="mt-1 text-sm whitespace-pre-line text-ink-800">{{ $note->content }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($consultation->diagnoses->isNotEmpty())
                <section class="k-card">
                    <div class="k-card-header"><h2 class="k-card-title">Diagnostics</h2></div>
                    <div class="overflow-x-auto">
                        <table class="k-table">
                            <caption class="sr-only">Diagnostics posés lors de la consultation</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Diagnostic</th>
                                    <th scope="col">Code</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Statut</th>
                                    <th scope="col">Médecin</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($consultation->diagnoses as $diagnosis)
                                    <tr>
                                        <td class="font-medium text-ink-900">{{ $diagnosis->label }}</td>
                                        <td class="font-mono text-xs">{{ $diagnosis->code ?: '-' }}</td>
                                        <td>
                                            {{ match ($diagnosis->type) {
                                                'primary' => 'Principal',
                                                'secondary' => 'Associé',
                                                default => 'Différentiel',
                                            } }}
                                        </td>
                                        <td><x-dme::status-badge :status="$diagnosis->status" :label="$diagnosis->statusLabel()"/></td>
                                        <td class="text-xs text-ink-500">{{ $diagnosis->doctor?->displayName() ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            <section class="k-card">
                <div class="k-card-header"><h2 class="k-card-title">Traitement et plan de soins</h2></div>
                <div class="k-card-body grid gap-4 sm:grid-cols-3">
                    @foreach ([
                        'Traitement' => $consultation->treatment_plan,
                        'Examens et suivi' => $consultation->follow_up,
                        'Recommandations' => $consultation->recommendations,
                    ] as $label => $value)
                        <div>
                            <h3 class="text-xs font-semibold tracking-wide text-ink-500 uppercase">{{ $label }}</h3>
                            <p class="mt-1 text-sm whitespace-pre-line text-ink-800">{{ $value ?: 'Non renseigné' }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="space-y-4">
            <section class="k-card">
                <div class="k-card-header"><h2 class="k-card-title">Constantes du jour</h2></div>
                <div class="k-card-body">
                    @php $v = $consultation->vitalSigns->first(); @endphp
                    @if ($v)
                        <div class="grid grid-cols-2 gap-2.5">
                            <x-dme::vital-card label="Tension" :value="$v->bloodPressure()" unit="mmHg"
                                          :abnormal="$v->isOutOfRange('systolic')"/>
                            <x-dme::vital-card label="Pouls" :value="$v->heart_rate" unit="bpm"
                                          :abnormal="$v->isOutOfRange('heart_rate')"/>
                            <x-dme::vital-card label="Température" :value="$v->temperature" unit="°C"
                                          :abnormal="$v->isOutOfRange('temperature')"/>
                            <x-dme::vital-card label="SpO₂" :value="$v->oxygen_saturation" unit="%"
                                          :abnormal="$v->isOutOfRange('oxygen_saturation')"/>
                            <x-dme::vital-card label="Poids" :value="$v->weight" unit="kg"/>
                            <x-dme::vital-card label="IMC" :value="$v->bmi" unit="kg/m²"/>
                        </div>
                    @else
                        <p class="text-sm text-ink-500">Aucune constante relevée pour cette consultation.</p>
                    @endif
                </div>
            </section>

            <section class="k-card">
                <div class="k-card-header"><h2 class="k-card-title">Actes rattachés</h2></div>
                <div class="k-card-body space-y-3 text-sm">
                    <div>
                        <h3 class="text-xs font-semibold tracking-wide text-ink-500 uppercase">Ordonnances</h3>
                        @forelse ($consultation->prescriptions as $prescription)
                            <a href="{{ route('dme.prescriptions.show', $prescription) }}"
                               class="mt-1 block font-mono text-xs text-clinic-700 hover:underline">
                                {{ $prescription->prescription_number }}
                                <span class="font-sans text-ink-500">
                                    - {{ $prescription->items->pluck('medication_name')->implode(', ') }}
                                </span>
                            </a>
                        @empty
                            <p class="mt-1 text-ink-500">Aucune</p>
                        @endforelse
                    </div>
                    <div>
                        <h3 class="text-xs font-semibold tracking-wide text-ink-500 uppercase">Laboratoire</h3>
                        @forelse ($consultation->labOrders as $order)
                            <a href="{{ route('dme.laboratory.show', $order) }}"
                               class="mt-1 block font-mono text-xs text-clinic-700 hover:underline">
                                {{ $order->order_number }}
                                <span class="font-sans text-ink-500">- {{ $order->items->count() }} examen(s)</span>
                            </a>
                        @empty
                            <p class="mt-1 text-ink-500">Aucun</p>
                        @endforelse
                    </div>
                    <div>
                        <h3 class="text-xs font-semibold tracking-wide text-ink-500 uppercase">Imagerie</h3>
                        @forelse ($consultation->imagingOrders as $order)
                            <a href="{{ route('dme.imaging.show', $order) }}"
                               class="mt-1 block text-xs text-clinic-700 hover:underline">
                                {{ $order->modalityLabel() }} - {{ $order->order_number }}
                            </a>
                        @empty
                            <p class="mt-1 text-ink-500">Aucune</p>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="k-card">
                <div class="k-card-header"><h2 class="k-card-title">Praticien</h2></div>
                <div class="k-card-body text-sm">
                    <p class="font-medium text-ink-900">{{ $consultation->doctor?->displayName() ?? '-' }}</p>
                    @if ($consultation->doctor?->speciality)
                        <p class="text-ink-500">{{ $consultation->doctor->speciality }}</p>
                    @endif
                    <p class="mt-2 text-xs text-ink-500">
                        Service : {{ $consultation->service?->name ?? 'non précisé' }}
                    </p>
                    @if ($consultation->ended_at)
                        <p class="text-xs text-ink-500">
                            Clôturée le {{ $consultation->ended_at->translatedFormat('d M Y à H:i') }}
                        </p>
                    @endif
                </div>
            </section>
        </div>
    </div>
@endsection
