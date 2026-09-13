{{-- Résumé médical (§15) --}}
<div class="grid gap-4 lg:grid-cols-3">

    {{-- Colonne principale --}}
    <div class="space-y-4 lg:col-span-2">

        {{-- Constantes récentes (§15) --}}
        <section class="k-card">
            <div class="k-card-header">
                <h2 class="k-card-title">Constantes récentes</h2>
                @if ($tabData['latestVitals'])
                    <span class="text-xs text-ink-500">
                        Relevé du {{ $tabData['latestVitals']->measured_at->translatedFormat('d M Y à H:i') }}
                    </span>
                @endif
            </div>
            <div class="k-card-body">
                @if ($v = $tabData['latestVitals'])
                    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                        <x-dme::vital-card label="Tension artérielle" :value="$v->bloodPressure()" unit="mmHg"
                                      :abnormal="$v->isOutOfRange('systolic') || $v->isOutOfRange('diastolic')"/>
                        <x-dme::vital-card label="Pouls" :value="$v->heart_rate" unit="bpm"
                                      :abnormal="$v->isOutOfRange('heart_rate')"/>
                        <x-dme::vital-card label="Température" :value="$v->temperature" unit="°C"
                                      :abnormal="$v->isOutOfRange('temperature')"/>
                        <x-dme::vital-card label="SpO₂" :value="$v->oxygen_saturation" unit="%"
                                      :abnormal="$v->isOutOfRange('oxygen_saturation')"/>
                        <x-dme::vital-card label="Poids" :value="$v->weight" unit="kg"/>
                        <x-dme::vital-card label="Taille" :value="$v->height" unit="cm"/>
                        <x-dme::vital-card label="IMC" :value="$v->bmi" unit="kg/m²"
                                      :hint="$v->bmi ? 'Calculé automatiquement' : null"/>
                        <x-dme::vital-card label="Glycémie" :value="$v->glycemia" unit="g/L"
                                      :abnormal="$v->isOutOfRange('glycemia')"/>
                    </div>

                    {{-- Courbes d'évolution (§20) --}}
                    @if ($tabData['vitalsHistory']->count() >= 2)
                        <div class="mt-5 grid gap-5 border-t border-ink-100 pt-4 sm:grid-cols-3">
                            <x-dme::sparkline label="Poids" unit="kg"
                                :points="$tabData['vitalsHistory']->map(fn ($r) => ['value' => $r->weight])->all()"/>
                            <x-dme::sparkline label="Tension systolique" unit="mmHg"
                                :points="$tabData['vitalsHistory']->map(fn ($r) => ['value' => $r->systolic])->all()"/>
                            <x-dme::sparkline label="Glycémie" unit="g/L"
                                :points="$tabData['vitalsHistory']->map(fn ($r) => ['value' => $r->glycemia])->all()"/>
                        </div>
                    @endif
                @else
                    <x-dme::empty-state icon="heart" title="Aucune constante enregistrée"
                                   message="Les constantes saisies en consultation ou par l'équipe soignante apparaîtront ici."/>
                @endif
            </div>
        </section>

        {{-- Dernières consultations (§15) --}}
        <section class="k-card">
            <div class="k-card-header">
                <h2 class="k-card-title">Dernières consultations</h2>
                <a href="{{ route('dme.patients.show', [$patient, 'tab' => 'consultations']) }}"
                   class="text-xs font-medium text-clinic-700 hover:underline">Tout voir</a>
            </div>
            <div class="k-card-body">
                @forelse ($tabData['lastConsultations'] as $consultation)
                    <a href="{{ route('dme.consultations.show', $consultation) }}"
                       class="flex items-start gap-3 border-b border-ink-100 py-2.5 first:pt-0 last:border-0 last:pb-0 hover:bg-clinic-50/40">
                        <span class="w-16 shrink-0 pt-0.5 text-xs text-ink-500">
                            {{ $consultation->started_at->translatedFormat('d M Y') }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-ink-900">{{ $consultation->reason ?: $consultation->typeLabel() }}</p>
                            <p class="text-xs text-ink-500">
                                {{ $consultation->doctor?->displayName() ?? 'Praticien non renseigné' }}
                                · {{ $consultation->consultation_number }}
                            </p>
                        </div>
                        <x-dme::status-badge :status="$consultation->status" :label="$consultation->statusLabel()"/>
                    </a>
                @empty
                    <x-dme::empty-state icon="stethoscope" title="Aucune consultation enregistrée"
                                   message="Ce patient n'a pas encore été vu en consultation.">
                        <x-slot:action>
                            @can('consultations.create')
                                <a href="{{ route('dme.consultations.create', $patient) }}" class="k-btn-primary">
                                    <x-dme::icon name="plus" class="h-4 w-4"/> Nouvelle consultation
                                </a>
                            @endcan
                        </x-slot:action>
                    </x-dme::empty-state>
                @endforelse
            </div>
        </section>

        {{-- Derniers examens (§15) --}}
        <section class="k-card">
            <div class="k-card-header">
                <h2 class="k-card-title">Derniers résultats d'examens</h2>
                <a href="{{ route('dme.patients.show', [$patient, 'tab' => 'laboratoire']) }}"
                   class="text-xs font-medium text-clinic-700 hover:underline">Laboratoire</a>
            </div>
            <div class="k-card-body">
                @forelse ($tabData['recentResults'] as $result)
                    <div class="flex items-center gap-3 border-b border-ink-100 py-2 first:pt-0 last:border-0 last:pb-0">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-ink-900">{{ $result->parameter }}</p>
                            <p class="text-xs text-ink-500">
                                {{ $result->item?->exam_name }}
                                @if ($result->measured_at) · {{ $result->measured_at->translatedFormat('d M Y') }} @endif
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold tabular-nums text-ink-900">
                                {{ $result->value }} {{ $result->unit }}
                            </p>
                            @if ($result->reference_range)
                                <p class="text-[11px] text-ink-400">Réf. {{ $result->reference_range }}</p>
                            @endif
                        </div>
                        <x-dme::status-badge :status="$result->flag" :label="$result->flagLabel()"/>
                    </div>
                @empty
                    <x-dme::empty-state icon="flask" title="Aucun résultat disponible"
                                   message="Les résultats validés par le laboratoire apparaîtront ici."/>
                @endforelse
            </div>
        </section>
    </div>

    {{-- Colonne latérale --}}
    <div class="space-y-4">

        <section class="k-card">
            <div class="k-card-header"><h2 class="k-card-title">Suivi</h2></div>
            <div class="k-card-body space-y-3 text-sm">
                <div>
                    <p class="text-xs text-ink-500">Dernière consultation</p>
                    <p class="font-medium text-ink-900">
                        {{ $tabData['lastConsultations']->first()?->started_at->translatedFormat('d F Y') ?? 'Aucune' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-ink-500">Prochain rendez-vous</p>
                    @if ($next = $tabData['nextAppointment'])
                        <a href="{{ route('dme.appointments.show', $next) }}" class="font-medium text-clinic-700 hover:underline">
                            {{ $next->scheduled_for->translatedFormat('d F Y à H:i') }}
                        </a>
                        <p class="text-xs text-ink-500">{{ $next->reason ?: 'Consultation' }}</p>
                    @else
                        <p class="font-medium text-ink-900">Aucun rendez-vous programmé</p>
                    @endif
                </div>
                <div>
                    <p class="text-xs text-ink-500">Médecin traitant</p>
                    <p class="font-medium text-ink-900">
                        {{ $patient->attendingDoctor?->displayName() ?? 'Non attribué' }}
                    </p>
                </div>
                @if ($patient->emergencyContacts->isNotEmpty())
                    <div>
                        <p class="text-xs text-ink-500">Personne à prévenir</p>
                        @foreach ($patient->emergencyContacts as $contact)
                            <p class="font-medium text-ink-900">{{ $contact->name }}</p>
                            <p class="text-xs text-ink-500">
                                {{ $contact->relationship ?: 'Proche' }}{{ $contact->phone ? ' · '.$contact->phone : '' }}
                            </p>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        <section class="k-card">
            <div class="k-card-header"><h2 class="k-card-title">Problèmes actifs</h2></div>
            <div class="k-card-body">
                @forelse ($tabData['activeProblems'] as $problem)
                    <div class="flex items-start justify-between gap-2 border-b border-ink-100 py-2 first:pt-0 last:border-0 last:pb-0">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-ink-900">{{ $problem->label }}</p>
                            <p class="text-xs text-ink-500">
                                @if ($problem->code) <span class="font-mono">{{ $problem->code }}</span> · @endif
                                {{ $problem->diagnosed_on?->translatedFormat('M Y') }}
                            </p>
                        </div>
                        <x-dme::status-badge :status="$problem->status" :label="$problem->statusLabel()"/>
                    </div>
                @empty
                    <p class="py-2 text-sm text-ink-500">Aucun problème actif documenté.</p>
                @endforelse
            </div>
        </section>

        <section class="k-card">
            <div class="k-card-header">
                <h2 class="k-card-title">Dernières ordonnances</h2>
                <a href="{{ route('dme.patients.show', [$patient, 'tab' => 'ordonnances']) }}"
                   class="text-xs font-medium text-clinic-700 hover:underline">Tout voir</a>
            </div>
            <div class="k-card-body">
                @forelse ($tabData['recentPrescriptions'] as $prescription)
                    <a href="{{ route('dme.prescriptions.show', $prescription) }}"
                       class="block border-b border-ink-100 py-2 first:pt-0 last:border-0 last:pb-0 hover:bg-clinic-50/40">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-mono text-xs text-ink-600">{{ $prescription->prescription_number }}</span>
                            <x-dme::status-badge :status="$prescription->status" :label="$prescription->statusLabel()"/>
                        </div>
                        <p class="mt-0.5 truncate text-sm text-ink-800">
                            {{ $prescription->items->pluck('medication_name')->implode(', ') ?: 'Aucun médicament' }}
                        </p>
                    </a>
                @empty
                    <p class="py-2 text-sm text-ink-500">Aucune ordonnance enregistrée.</p>
                @endforelse
            </div>
        </section>
    </div>
</div>
