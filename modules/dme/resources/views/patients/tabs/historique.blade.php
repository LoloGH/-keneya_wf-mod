{{-- Historique médical chronologique (§29) --}}
@php $filters = $tabData['activeFilters']; @endphp

<section class="k-card">
    <div class="k-card-header">
        <h2 class="k-card-title">Historique médical</h2>
        <span class="text-xs text-ink-500">{{ $tabData['timeline']->flatten(1)->count() }} événement(s) affiché(s)</span>
    </div>

    {{-- Filtres par type d'événement (§29) --}}
    <form method="GET" action="{{ route('dme.patients.show', $patient) }}" class="border-b border-ink-100 px-4 py-3">
        <input type="hidden" name="tab" value="historique">
        <input type="hidden" name="limit" value="{{ $tabData['limit'] }}">
        <fieldset>
            <legend class="k-label">Filtrer par type</legend>
            <div class="flex flex-wrap gap-2">
                @foreach (\Keneya\Dme\Services\Patients\MedicalTimeline::FILTER_LABELS as $value => $label)
                    <label class="flex cursor-pointer items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition
                        {{ in_array($value, $filters, true) ? 'border-clinic-500 bg-clinic-50 text-clinic-700' : 'border-ink-300 text-ink-600 hover:bg-ink-50' }}">
                        <input type="checkbox" name="filters[]" value="{{ $value }}"
                               @checked(in_array($value, $filters, true))
                               class="h-3.5 w-3.5 rounded border-ink-300 text-clinic-600">
                        {{ $label }}
                    </label>
                @endforeach
                <button type="submit" class="k-btn-secondary k-btn-sm">Appliquer</button>
                @if ($filters)
                    <a href="{{ route('dme.patients.show', [$patient, 'tab' => 'historique']) }}" class="k-btn-ghost k-btn-sm">
                        Tout afficher
                    </a>
                @endif
            </div>
        </fieldset>
    </form>

    <div class="k-card-body">
        @if ($tabData['timeline']->isEmpty())
            <x-dme::empty-state icon="clipboard" title="Aucun événement dans l'historique"
                           message="Consultations, prescriptions, examens et hospitalisations viendront alimenter cette chronologie."/>
        @else
            <x-dme::timeline :groups="$tabData['timeline']"/>

            {{-- Chargement progressif (§58) --}}
            @if ($tabData['timeline']->flatten(1)->count() >= $tabData['limit'])
                <div class="mt-6 text-center">
                    <a href="{{ route('dme.patients.show', array_merge(
                            ['patient' => $patient, 'tab' => 'historique', 'limit' => $tabData['limit'] + 20],
                            $filters ? ['filters' => $filters] : []
                        )) }}"
                       class="k-btn-secondary">Charger davantage d'événements</a>
                </div>
            @endif
        @endif
    </div>
</section>
