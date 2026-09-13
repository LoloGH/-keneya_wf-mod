{{-- Consultations du patient (§14) --}}
<section class="k-card">
    <div class="k-card-header">
        <h2 class="k-card-title">Consultations</h2>
        @can('consultations.create')
            <a href="{{ route('dme.consultations.create', $patient) }}" class="k-btn-primary k-btn-sm">
                <x-dme::icon name="plus" class="h-3.5 w-3.5"/> Nouvelle consultation
            </a>
        @endcan
    </div>

    @if ($tabData['consultations']->isEmpty())
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
    @else
        <div class="overflow-x-auto">
            <table class="k-table">
                <caption class="sr-only">Consultations du patient</caption>
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">N°</th>
                        <th scope="col">Motif</th>
                        <th scope="col">Médecin</th>
                        <th scope="col">Service</th>
                        <th scope="col">Diagnostics</th>
                        <th scope="col">Statut</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tabData['consultations'] as $consultation)
                        <tr>
                            <td class="whitespace-nowrap">{{ $consultation->started_at->translatedFormat('d M Y') }}</td>
                            <td class="font-mono text-xs">{{ $consultation->consultation_number }}</td>
                            <td class="max-w-xs truncate">{{ $consultation->reason ?: $consultation->typeLabel() }}</td>
                            <td>{{ $consultation->doctor?->displayName() ?? '-' }}</td>
                            <td>{{ $consultation->service?->name ?? '-' }}</td>
                            <td class="tabular-nums">{{ $consultation->diagnoses_count }}</td>
                            <td><x-dme::status-badge :status="$consultation->status" :label="$consultation->statusLabel()"/></td>
                            <td class="text-right">
                                <a href="{{ route('dme.consultations.show', $consultation) }}" class="k-btn-ghost k-btn-sm">
                                    Ouvrir <x-dme::icon name="chevron-right" class="h-3.5 w-3.5"/>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $tabData['consultations']->links() }}</div>
    @endif
</section>
