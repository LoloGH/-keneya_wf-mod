{{-- Imagerie (§24) --}}
<section class="k-card">
    <div class="k-card-header">
        <h2 class="k-card-title">Examens d'imagerie</h2>
        @can('imaging.create')
            <a href="{{ route('dme.imaging.create', $patient) }}" class="k-btn-primary k-btn-sm">
                <x-dme::icon name="plus" class="h-3.5 w-3.5"/> Nouvelle demande
            </a>
        @endcan
    </div>

    @if ($tabData['imagingOrders']->isEmpty())
        <x-dme::empty-state icon="scan" title="Aucun examen d'imagerie"
                       message="Les demandes d'imagerie et leurs comptes rendus apparaîtront ici."/>
    @else
        <ul class="divide-y divide-ink-100">
            @foreach ($tabData['imagingOrders'] as $order)
                <li class="p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('dme.imaging.show', $order) }}"
                                   class="text-sm font-medium text-clinic-700 hover:underline">
                                    {{ $order->modalityLabel() }}{{ $order->body_site ? ' - '.$order->body_site : '' }}
                                </a>
                                <x-dme::status-badge :status="$order->status" :label="$order->statusLabel()"/>
                            </div>
                            <p class="mt-0.5 text-xs text-ink-500">
                                <span class="font-mono">{{ $order->order_number }}</span>
                                · {{ $order->requested_at->translatedFormat('d M Y') }}
                                · {{ $order->doctor?->displayName() ?? '-' }}
                            </p>
                            @if ($order->indication)
                                <p class="mt-1 text-sm text-ink-600"><span class="text-ink-400">Indication :</span> {{ $order->indication }}</p>
                            @endif
                            @if ($order->report?->conclusion)
                                <div class="mt-2 rounded-lg bg-ink-50 p-3">
                                    <p class="text-xs font-semibold text-ink-500">Conclusion</p>
                                    <p class="mt-0.5 text-sm text-ink-800">{{ $order->report->conclusion }}</p>
                                </div>
                            @endif
                        </div>
                        <a href="{{ route('dme.imaging.show', $order) }}" class="k-btn-ghost k-btn-sm">
                            Détail <x-dme::icon name="chevron-right" class="h-3.5 w-3.5"/>
                        </a>
                    </div>
                </li>
            @endforeach
        </ul>
        <div class="p-4">{{ $tabData['imagingOrders']->links() }}</div>
    @endif
</section>
