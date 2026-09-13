{{-- Hospitalisations (§25) --}}
<section class="k-card">
    <div class="k-card-header">
        <h2 class="k-card-title">Hospitalisations</h2>
        @can('hospitalizations.create')
            <a href="{{ route('dme.hospitalizations.create', $patient) }}" class="k-btn-primary k-btn-sm">
                <x-dme::icon name="plus" class="h-3.5 w-3.5"/> Nouvelle admission
            </a>
        @endcan
    </div>

    @if ($tabData['hospitalizations']->isEmpty())
        <x-dme::empty-state icon="bed" title="Aucune hospitalisation"
                       message="Les séjours hospitaliers de ce patient apparaîtront ici."/>
    @else
        <ul class="divide-y divide-ink-100">
            @foreach ($tabData['hospitalizations'] as $stay)
                <li class="flex flex-wrap items-start justify-between gap-3 p-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('dme.hospitalizations.show', $stay) }}"
                               class="font-mono text-sm font-medium text-clinic-700 hover:underline">
                                {{ $stay->hospitalization_number }}
                            </a>
                            <x-dme::status-badge :status="$stay->status" :label="$stay->statusLabel()"/>
                        </div>
                        <p class="mt-1 text-sm text-ink-800">
                            {{ $stay->admitted_at->translatedFormat('d M Y') }}
                            @if ($stay->discharged_at)
                                -> {{ $stay->discharged_at->translatedFormat('d M Y') }}
                            @else
                                -> en cours
                            @endif
                            <span class="text-ink-400">· {{ $stay->lengthOfStay() }} jour(s)</span>
                        </p>
                        <p class="text-xs text-ink-500">
                            {{ $stay->service?->name ?? 'Service non renseigné' }}
                            @if ($stay->room) · Chambre {{ $stay->room }} @endif
                            @if ($stay->bed) · {{ $stay->bed }} @endif
                        </p>
                        <p class="mt-1 text-sm text-ink-600">
                            {{ $stay->discharge_diagnosis ?: $stay->admission_diagnosis ?: $stay->admission_reason }}
                        </p>
                    </div>
                    <a href="{{ route('dme.hospitalizations.show', $stay) }}" class="k-btn-ghost k-btn-sm">
                        Suivi <x-dme::icon name="chevron-right" class="h-3.5 w-3.5"/>
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="p-4">{{ $tabData['hospitalizations']->links() }}</div>
    @endif
</section>
