@extends('dme::pdf._layout', ['title' => 'Compte rendu de consultation'])

@section('content')
    <h2>Consultation</h2>
    <table width="100%">
        <tr>
            <td width="50%" class="small">
                <span class="muted">Date :</span> {{ $consultation->started_at->format('d/m/Y à H:i') }}<br>
                <span class="muted">Type :</span> {{ $consultation->typeLabel() }}<br>
                <span class="muted">Service :</span> {{ $consultation->service?->name ?? 'Non précisé' }}
            </td>
            <td width="50%" class="small">
                <span class="muted">Médecin :</span> {{ $consultation->doctor?->displayName() ?? '-' }}<br>
                <span class="muted">Spécialité :</span> {{ $consultation->doctor?->speciality ?? '-' }}<br>
                <span class="muted">Statut :</span> {{ $consultation->statusLabel() }}
            </td>
        </tr>
    </table>

    @if ($consultation->reason)
        <h2>Motif</h2>
        <div>{{ $consultation->reason }}</div>
    @endif

    @if ($consultation->history_of_illness)
        <h2>Histoire de la maladie</h2>
        <div>{!! nl2br(e($consultation->history_of_illness)) !!}</div>
    @endif

    @php $vital = $consultation->vitalSigns->first(); @endphp
    @if ($vital)
        <h2>Constantes vitales</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Tension</th><th>Pouls</th><th>Température</th><th>SpO₂</th>
                    <th>Poids</th><th>Taille</th><th>IMC</th><th>Glycémie</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $vital->bloodPressure() ?: '-' }} mmHg</td>
                    <td>{{ $vital->heart_rate ?: '-' }} bpm</td>
                    <td>{{ $vital->temperature ?: '-' }} °C</td>
                    <td>{{ $vital->oxygen_saturation ?: '-' }} %</td>
                    <td>{{ $vital->weight ?: '-' }} kg</td>
                    <td>{{ $vital->height ?: '-' }} cm</td>
                    <td>{{ $vital->bmi ?: '-' }}</td>
                    <td>{{ $vital->glycemia ?: '-' }} g/L</td>
                </tr>
            </tbody>
        </table>
    @endif

    @if ($consultation->clinicalNotes->isNotEmpty())
        <h2>Examen clinique</h2>
        <table class="data">
            <tbody>
                @foreach ($consultation->clinicalNotes as $note)
                    <tr>
                        <td width="22%" class="strong">{{ $note->systemLabel() }}</td>
                        <td>{{ $note->content }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($consultation->diagnoses->isNotEmpty())
        <h2>Diagnostics</h2>
        <table class="data">
            <thead>
                <tr><th width="50%">Diagnostic</th><th width="15%">Code</th><th width="17%">Type</th><th width="18%">Statut</th></tr>
            </thead>
            <tbody>
                @foreach ($consultation->diagnoses as $diagnosis)
                    <tr>
                        <td class="strong">{{ $diagnosis->label }}</td>
                        <td>{{ $diagnosis->code ?: '-' }}</td>
                        <td>{{ match ($diagnosis->type) {
                            'primary' => 'Principal', 'secondary' => 'Associé', default => 'Différentiel',
                        } }}</td>
                        <td>{{ $diagnosis->statusLabel() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @foreach ([
        'Traitement' => $consultation->treatment_plan,
        'Examens et suivi' => $consultation->follow_up,
        'Recommandations' => $consultation->recommendations,
    ] as $label => $value)
        @if ($value)
            <h2>{{ $label }}</h2>
            <div>{!! nl2br(e($value)) !!}</div>
        @endif
    @endforeach

    <div class="signature">
        <div class="signature-line">
            {{ $consultation->doctor?->displayName() ?? '' }}<br>
            <span class="muted">{{ $consultation->doctor?->speciality ?? '' }}</span>
        </div>
    </div>
@endsection
