@extends('dme::pdf._layout', ['title' => 'Compte rendu d\'hospitalisation'])

@section('content')
    <h2>Séjour</h2>
    <table width="100%">
        <tr>
            <td width="50%" class="small">
                <span class="muted">Admission :</span> {{ $hospitalization->admitted_at->format('d/m/Y à H:i') }}<br>
                <span class="muted">Sortie :</span>
                {{ $hospitalization->discharged_at?->format('d/m/Y à H:i') ?? 'En cours' }}<br>
                <span class="muted">Durée :</span> {{ $hospitalization->lengthOfStay() }} jour(s)
            </td>
            <td width="50%" class="small">
                <span class="muted">Service :</span> {{ $hospitalization->service?->name ?? '-' }}<br>
                <span class="muted">Médecin :</span> {{ $hospitalization->doctor?->displayName() ?? '-' }}<br>
                <span class="muted">Chambre :</span> {{ $hospitalization->room ?: '-' }}
                {{ $hospitalization->bed ? '· '.$hospitalization->bed : '' }}
            </td>
        </tr>
    </table>

    <h2>Motif d'admission</h2>
    <div>{{ $hospitalization->admission_reason }}</div>
    @if ($hospitalization->admission_diagnosis)
        <div class="small muted">Diagnostic d'entrée : {{ $hospitalization->admission_diagnosis }}</div>
    @endif

    @if ($hospitalization->events->isNotEmpty())
        <h2>Déroulé du séjour</h2>
        <table class="data">
            <thead>
                <tr><th width="18%">Date</th><th width="16%">Type</th><th width="66%">Événement</th></tr>
            </thead>
            <tbody>
                @foreach ($hospitalization->events as $event)
                    <tr>
                        <td class="small">{{ $event->occurred_at->format('d/m/Y H:i') }}</td>
                        <td class="small">{{ $event->typeLabel() }}</td>
                        <td>
                            <span class="strong">{{ $event->title }}</span>
                            @if ($event->content)<br><span class="small muted">{{ $event->content }}</span>@endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @foreach ([
        'Diagnostic de sortie' => $hospitalization->discharge_diagnosis,
        'Synthèse du séjour' => $hospitalization->discharge_summary,
        'Traitement de sortie' => $hospitalization->discharge_treatment,
        'Recommandations' => $hospitalization->discharge_recommendations,
    ] as $label => $value)
        @if ($value)
            <h2>{{ $label }}</h2>
            <div>{!! nl2br(e($value)) !!}</div>
        @endif
    @endforeach

    <div class="signature">
        <div class="signature-line">
            {{ $hospitalization->doctor?->displayName() ?? '' }}<br>
            <span class="muted">Médecin responsable du séjour</span>
        </div>
    </div>
@endsection
