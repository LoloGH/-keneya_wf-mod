@extends('dme::pdf._layout', ['title' => 'Compte rendu de laboratoire'])

@section('content')
    <h2>Demande</h2>
    <table width="100%">
        <tr>
            <td width="50%" class="small">
                <span class="muted">Demandée le :</span> {{ $order->requested_at->format('d/m/Y à H:i') }}<br>
                <span class="muted">Prescripteur :</span> {{ $order->doctor?->displayName() ?? '-' }}<br>
                <span class="muted">Urgence :</span> {{ $order->priorityLabel() }}
            </td>
            <td width="50%" class="small">
                <span class="muted">Statut :</span> {{ $order->statusLabel() }}<br>
                @if ($order->completed_at)
                    <span class="muted">Résultats du :</span> {{ $order->completed_at->format('d/m/Y à H:i') }}
                @endif
            </td>
        </tr>
    </table>

    @if ($order->indication)
        <div class="panel small"><span class="muted">Indication :</span> {{ $order->indication }}</div>
    @endif

    <h2>Résultats</h2>
    <table class="data">
        <thead>
            <tr>
                <th width="26%">Examen</th>
                <th width="20%">Paramètre</th>
                <th width="12%">Résultat</th>
                <th width="10%">Unité</th>
                <th width="18%">Valeurs de référence</th>
                <th width="14%">Interprétation</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                @forelse ($item->results as $result)
                    <tr>
                        <td>{{ $item->exam_name }}</td>
                        <td class="strong">{{ $result->parameter }}</td>
                        <td class="strong">{{ $result->value }}</td>
                        <td>{{ $result->unit }}</td>
                        <td class="small muted">{{ $result->reference_range ?: '-' }}</td>
                        <td>{{ $result->flagLabel() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td>{{ $item->exam_name }}</td>
                        <td colspan="5" class="muted">Résultat en attente</td>
                    </tr>
                @endforelse
            @endforeach
        </tbody>
    </table>

    <p class="small muted">
        Les valeurs de référence sont celles en vigueur au moment de l'analyse. Toute interprétation
        doit tenir compte du contexte clinique du patient.
    </p>

    <div class="signature">
        <div class="signature-line">Biologiste : validation technique et biologique</div>
    </div>
@endsection
