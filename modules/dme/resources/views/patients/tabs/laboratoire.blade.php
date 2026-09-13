{{-- Laboratoire (§23) --}}
<section class="k-card">
    <div class="k-card-header">
        <h2 class="k-card-title">Examens de laboratoire</h2>
        @can('laboratory.orders.create')
            <a href="{{ route('dme.laboratory.create', $patient) }}" class="k-btn-primary k-btn-sm">
                <x-dme::icon name="plus" class="h-3.5 w-3.5"/> Nouvelle demande
            </a>
        @endcan
    </div>

    @if ($tabData['labOrders']->isEmpty())
        <x-dme::empty-state icon="flask" title="Aucune demande d'analyse"
                       message="Les demandes d'examens biologiques et leurs résultats apparaîtront ici."/>
    @else
        <ul class="divide-y divide-ink-100">
            @foreach ($tabData['labOrders'] as $order)
                <li class="p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('dme.laboratory.show', $order) }}"
                               class="font-mono text-sm font-medium text-clinic-700 hover:underline">
                                {{ $order->order_number }}
                            </a>
                            <x-dme::status-badge :status="$order->status" :label="$order->statusLabel()"/>
                            @if ($order->priority !== 'routine')
                                <x-dme::status-badge :status="$order->priority" :label="$order->priorityLabel()"/>
                            @endif
                        </div>
                        <span class="text-xs text-ink-500">
                            {{ $order->requested_at->translatedFormat('d M Y') }}
                            · {{ $order->doctor?->displayName() ?? '-' }}
                        </span>
                    </div>

                    <div class="mt-2 overflow-x-auto">
                        <table class="k-table">
                            <caption class="sr-only">Résultats de la demande {{ $order->order_number }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Examen</th>
                                    <th scope="col">Paramètre</th>
                                    <th scope="col">Résultat</th>
                                    <th scope="col">Unité</th>
                                    <th scope="col">Valeurs de référence</th>
                                    <th scope="col">Interprétation</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($order->items as $item)
                                    @forelse ($item->results as $result)
                                        <tr>
                                            <td>{{ $item->exam_name }}</td>
                                            <td class="font-medium text-ink-900">{{ $result->parameter }}</td>
                                            <td class="font-semibold tabular-nums">{{ $result->value }}</td>
                                            <td>{{ $result->unit }}</td>
                                            <td class="text-xs text-ink-500">{{ $result->reference_range ?: '-' }}</td>
                                            <td><x-dme::status-badge :status="$result->flag" :label="$result->flagLabel()"/></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td>{{ $item->exam_name }}</td>
                                            <td colspan="5" class="text-ink-500">Résultat en attente</td>
                                        </tr>
                                    @endforelse
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </li>
            @endforeach
        </ul>
        <div class="p-4">{{ $tabData['labOrders']->links() }}</div>
    @endif
</section>
