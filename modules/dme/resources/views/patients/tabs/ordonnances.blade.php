{{-- Ordonnances du patient (§22) --}}
<section class="k-card">
    <div class="k-card-header">
        <h2 class="k-card-title">Ordonnances</h2>
        @can('prescriptions.create')
            <a href="{{ route('dme.prescriptions.create', $patient) }}" class="k-btn-primary k-btn-sm">
                <x-dme::icon name="plus" class="h-3.5 w-3.5"/> Nouvelle ordonnance
            </a>
        @endcan
    </div>

    @if ($tabData['prescriptions']->isEmpty())
        <x-dme::empty-state icon="pill" title="Aucune ordonnance"
                       message="Les prescriptions établies pour ce patient apparaîtront ici."/>
    @else
        <ul class="divide-y divide-ink-100">
            @foreach ($tabData['prescriptions'] as $prescription)
                <li class="p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('dme.prescriptions.show', $prescription) }}"
                                   class="font-mono text-sm font-medium text-clinic-700 hover:underline">
                                    {{ $prescription->prescription_number }}
                                </a>
                                <x-dme::status-badge :status="$prescription->status" :label="$prescription->statusLabel()"/>
                                @if ($prescription->hasAllergyWarnings())
                                    <span class="k-badge-danger">
                                        <x-dme::icon name="alert" class="h-3 w-3"/> Alerte allergie
                                    </span>
                                @endif
                            </div>
                            <p class="mt-1 text-xs text-ink-500">
                                {{ $prescription->issued_on->translatedFormat('d F Y') }}
                                · {{ $prescription->doctor?->displayName() ?? 'Prescripteur non renseigné' }}
                            </p>
                            <ul class="mt-2 space-y-0.5 text-sm text-ink-700">
                                @foreach ($prescription->items as $item)
                                    <li>
                                        <span class="font-medium">{{ $item->medication_name }}</span>
                                        @if ($item->posology())
                                            <span class="text-ink-500">- {{ $item->posology() }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <a href="{{ route('dme.prescriptions.pdf', $prescription) }}" target="_blank" rel="noopener"
                           class="k-btn-secondary k-btn-sm">
                            <x-dme::icon name="print" class="h-3.5 w-3.5"/> PDF
                        </a>
                    </div>
                </li>
            @endforeach
        </ul>
        <div class="p-4">{{ $tabData['prescriptions']->links() }}</div>
    @endif
</section>
