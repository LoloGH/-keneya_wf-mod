@extends('dme::layouts.app')

@section('title', 'Séjour '.$hospitalization->hospitalization_number)

@section('content')
    <x-dme::page-header :title="'Séjour '.$hospitalization->hospitalization_number"
                   :subtitle="'Admis le '.$hospitalization->admitted_at->translatedFormat('d F Y à H:i').' · '.$hospitalization->lengthOfStay().' jour(s)'"
                   :breadcrumbs="[
                       'Hospitalisations' => route('dme.hospitalizations.index'),
                       $hospitalization->patient->fullName() => route('dme.patients.show', $hospitalization->patient),
                       $hospitalization->hospitalization_number => null,
                   ]">
        <x-slot:actions>
            @if ($hospitalization->status === 'discharged')
                <a href="{{ route('dme.hospitalizations.pdf', $hospitalization) }}" target="_blank" rel="noopener"
                   class="k-btn-secondary">
                    <x-dme::icon name="print" class="h-4 w-4"/> Compte rendu PDF
                </a>
            @endif
        </x-slot:actions>
    </x-dme::page-header>

    <section class="k-card mb-4">
        <div class="p-4 sm:p-5">
            <x-dme::patient-header :patient="$hospitalization->patient" compact/>
            @if ($hospitalization->patient->criticalAllergies()->isNotEmpty() || $hospitalization->patient->activeConditions()->isNotEmpty())
                <div class="mt-4"><x-dme::medical-alerts :patient="$hospitalization->patient"/></div>
            @endif
        </div>
    </section>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">

            {{-- Timeline du séjour (§25) --}}
            <section class="k-card">
                <div class="k-card-header">
                    <h2 class="k-card-title">Suivi du séjour</h2>
                    <x-dme::status-badge :status="$hospitalization->status" :label="$hospitalization->statusLabel()"/>
                </div>
                <div class="k-card-body">
                    <ol class="relative space-y-3 border-l border-ink-200 pl-5">
                        @foreach ($hospitalization->events as $event)
                            <li class="relative">
                                <span class="absolute top-2 -left-[27px] flex h-3 w-3 rounded-full border-2 border-white
                                    @class([
                                        'bg-keneya-500' => in_array($event->type, ['admission', 'discharge'], true),
                                        'bg-clinic-500' => ! in_array($event->type, ['admission', 'discharge'], true),
                                    ])"></span>
                                <div class="rounded-lg border border-ink-200 p-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-mono text-xs text-ink-500">
                                            {{ $event->occurred_at->translatedFormat('d M · H:i') }}
                                        </span>
                                        <span class="k-badge-info">{{ $event->typeLabel() }}</span>
                                    </div>
                                    <p class="mt-1.5 text-sm font-medium text-ink-900">{{ $event->title }}</p>
                                    @if ($event->content)
                                        <p class="mt-0.5 text-sm text-ink-600">{{ $event->content }}</p>
                                    @endif
                                    <p class="mt-1 text-xs text-ink-400">{{ $event->recorder?->displayName() ?? '-' }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>

            {{-- Ajout d'un événement (§25) --}}
            @can('update', $hospitalization)
                <section class="k-card">
                    <div class="k-card-header"><h2 class="k-card-title">Ajouter au suivi</h2></div>
                    <form action="{{ route('dme.hospitalizations.events.store', $hospitalization) }}" method="POST"
                          class="k-card-body grid gap-3 sm:grid-cols-2">
                        @csrf
                        <div>
                            <label for="event_type" class="k-label">Type <span class="text-red-600" aria-hidden="true">*</span></label>
                            <select id="event_type" name="type" required class="k-select">
                                @foreach (\Keneya\Dme\Models\HospitalizationEvent::TYPES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="event_occurred_at" class="k-label">Date et heure <span class="text-red-600" aria-hidden="true">*</span></label>
                            <input id="event_occurred_at" name="occurred_at" type="datetime-local" required
                                   value="{{ now()->format('Y-m-d\TH:i') }}" class="k-input">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="event_title" class="k-label">Intitulé <span class="text-red-600" aria-hidden="true">*</span></label>
                            <input id="event_title" name="title" type="text" required maxlength="200" class="k-input">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="event_content" class="k-label">Détail</label>
                            <textarea id="event_content" name="content" rows="3" maxlength="5000" class="k-textarea"></textarea>
                        </div>
                        <div class="sm:col-span-2">
                            <button type="submit" class="k-btn-primary">Ajouter l'événement</button>
                        </div>
                    </form>
                </section>
            @endcan

            {{-- Soins infirmiers du séjour (§26) --}}
            <section class="k-card">
                <div class="k-card-header"><h2 class="k-card-title">Soins infirmiers</h2></div>
                <div class="k-card-body">
                    @forelse ($hospitalization->nursingNotes as $note)
                        <div class="border-b border-ink-100 py-2.5 first:pt-0 last:border-0 last:pb-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-xs text-ink-500">
                                    {{ $note->occurred_at->translatedFormat('d M · H:i') }}
                                </span>
                                <span class="k-badge-neutral">{{ $note->typeLabel() }}</span>
                                @if ($note->severity !== 'info')
                                    <x-dme::status-badge :status="$note->severity"
                                        :label="$note->severity === 'critical' ? 'Critique' : 'Vigilance'"/>
                                @endif
                            </div>
                            <p class="mt-1 text-sm font-medium text-ink-900">{{ $note->title }}</p>
                            @if ($note->content)
                                <p class="text-sm text-ink-600">{{ $note->content }}</p>
                            @endif
                            <p class="text-xs text-ink-400">{{ $note->nurse?->displayName() ?? '-' }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-ink-500">Aucun soin enregistré pour ce séjour.</p>
                    @endforelse
                </div>
            </section>

            {{-- Sortie (§25) --}}
            @can('discharge', $hospitalization)
                <section class="k-card">
                    <div class="k-card-header"><h2 class="k-card-title">Enregistrer la sortie</h2></div>
                    <form action="{{ route('dme.hospitalizations.discharge', $hospitalization) }}" method="POST"
                          class="k-card-body grid gap-3 sm:grid-cols-2">
                        @csrf
                        <div>
                            <label for="discharged_at" class="k-label">Date de sortie <span class="text-red-600" aria-hidden="true">*</span></label>
                            <input id="discharged_at" name="discharged_at" type="datetime-local" required
                                   value="{{ now()->format('Y-m-d\TH:i') }}" class="k-input">
                            <x-dme::field-error name="discharged_at"/>
                        </div>
                        <div>
                            <label for="discharge_type" class="k-label">Mode de sortie <span class="text-red-600" aria-hidden="true">*</span></label>
                            <select id="discharge_type" name="discharge_type" required class="k-select">
                                <option value="home">Retour à domicile</option>
                                <option value="transfer">Transfert</option>
                                <option value="against_advice">Sortie contre avis médical</option>
                                <option value="deceased">Décès</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="discharge_diagnosis" class="k-label">Diagnostic de sortie <span class="text-red-600" aria-hidden="true">*</span></label>
                            <input id="discharge_diagnosis" name="discharge_diagnosis" type="text" required maxlength="200"
                                   class="k-input">
                            <x-dme::field-error name="discharge_diagnosis"/>
                        </div>
                        <div>
                            <label for="discharge_treatment" class="k-label">Traitement de sortie</label>
                            <textarea id="discharge_treatment" name="discharge_treatment" rows="3" maxlength="10000"
                                      class="k-textarea"></textarea>
                        </div>
                        <div>
                            <label for="discharge_recommendations" class="k-label">Recommandations</label>
                            <textarea id="discharge_recommendations" name="discharge_recommendations" rows="3"
                                      maxlength="10000" class="k-textarea"></textarea>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="discharge_summary" class="k-label">Compte rendu d'hospitalisation</label>
                            <textarea id="discharge_summary" name="discharge_summary" rows="5" maxlength="20000"
                                      class="k-textarea"></textarea>
                        </div>
                        <div class="sm:col-span-2">
                            <button type="submit" class="k-btn-primary">Enregistrer la sortie</button>
                        </div>
                    </form>
                </section>
            @endcan

            @if ($hospitalization->status === 'discharged')
                <section class="k-card">
                    <div class="k-card-header"><h2 class="k-card-title">Compte rendu de sortie</h2></div>
                    <div class="k-card-body space-y-3">
                        @foreach ([
                            'Diagnostic de sortie' => $hospitalization->discharge_diagnosis,
                            'Traitement' => $hospitalization->discharge_treatment,
                            'Recommandations' => $hospitalization->discharge_recommendations,
                            'Synthèse' => $hospitalization->discharge_summary,
                        ] as $label => $value)
                            @if ($value)
                                <div>
                                    <h3 class="text-xs font-semibold tracking-wide text-ink-500 uppercase">{{ $label }}</h3>
                                    <p class="mt-1 text-sm leading-relaxed whitespace-pre-line text-ink-800">{{ $value }}</p>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        <div class="space-y-4">
            <section class="k-card">
                <div class="k-card-header"><h2 class="k-card-title">Séjour</h2></div>
                <dl class="k-card-body space-y-2.5 text-sm">
                    <div>
                        <dt class="text-xs text-ink-500">Service</dt>
                        <dd class="font-medium text-ink-900">{{ $hospitalization->service?->name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-ink-500">Médecin responsable</dt>
                        <dd class="text-ink-800">{{ $hospitalization->doctor?->displayName() ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-ink-500">Localisation</dt>
                        <dd class="text-ink-800">
                            {{ $hospitalization->room ? 'Chambre '.$hospitalization->room : 'Non précisée' }}
                            @if ($hospitalization->bed) · {{ $hospitalization->bed }} @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-ink-500">Motif d'admission</dt>
                        <dd class="text-ink-800">{{ $hospitalization->admission_reason }}</dd>
                    </div>
                </dl>
            </section>

            <section class="k-card">
                <div class="k-card-header"><h2 class="k-card-title">Constantes récentes</h2></div>
                <div class="k-card-body">
                    @forelse ($vitals as $vital)
                        <div class="flex items-center justify-between gap-2 border-b border-ink-100 py-2 text-sm first:pt-0 last:border-0 last:pb-0">
                            <span class="text-xs text-ink-500">{{ $vital->measured_at->translatedFormat('d M · H:i') }}</span>
                            <span class="tabular-nums text-ink-800">
                                {{ $vital->bloodPressure() ?: '-' }} mmHg
                                @if ($vital->heart_rate) · {{ $vital->heart_rate }} bpm @endif
                            </span>
                        </div>
                    @empty
                        <p class="text-sm text-ink-500">Aucune constante enregistrée.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
