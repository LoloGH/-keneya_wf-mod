@extends('dme::pdf._layout', ['title' => 'Ordonnance'])

@section('content')
    {{-- Alerte allergie reportée sur le document imprimé (§22) --}}
    @if ($prescription->hasAllergyWarnings())
        <div class="alert">
            <div class="alert-title">Allergies documentées, vigilance</div>
            @foreach ($prescription->allergy_warnings as $warning)
                <div class="small">
                    {{ $warning['medication'] }} ↔ {{ $warning['allergen'] }} ({{ $warning['severity_label'] }})
                </div>
            @endforeach
        </div>
    @elseif ($patient->criticalAllergies()->isNotEmpty())
        <div class="alert">
            <div class="alert-title">Allergies connues du patient</div>
            @foreach ($patient->criticalAllergies() as $allergy)
                <div class="small">{{ $allergy->allergen }} - {{ $allergy->severityLabel() }}</div>
            @endforeach
        </div>
    @endif

    <h2>Prescripteur</h2>
    <table width="100%">
        <tr>
            <td width="60%">
                <span class="strong">{{ $prescription->doctor?->displayName() ?? '-' }}</span><br>
                <span class="small muted">{{ $prescription->doctor?->speciality }}</span>
            </td>
            <td width="40%" class="small muted">
                Date de prescription : {{ $prescription->issued_on->format('d/m/Y') }}<br>
                @if ($prescription->valid_until)
                    Valable jusqu'au {{ $prescription->valid_until->format('d/m/Y') }}
                @endif
            </td>
        </tr>
    </table>

    <h2>Prescription</h2>
    <table class="data">
        <thead>
            <tr>
                <th width="4%">#</th>
                <th width="30%">Médicament</th>
                <th width="14%">Posologie</th>
                <th width="14%">Fréquence</th>
                <th width="12%">Durée</th>
                <th width="12%">Quantité</th>
                <th width="14%">Voie</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($prescription->items as $item)
                <tr>
                    <td>{{ $item->position }}</td>
                    <td>
                        <span class="strong">{{ $item->medication_name }}</span>
                        @if ($item->form)<br><span class="small muted">{{ $item->form }}</span>@endif
                        @if ($item->instructions)<br><span class="small muted">{{ $item->instructions }}</span>@endif
                    </td>
                    <td>{{ $item->dosage ?: '-' }}</td>
                    <td>{{ $item->frequency ?: '-' }}</td>
                    <td>{{ $item->duration ?: '-' }}</td>
                    <td>{{ $item->quantity ?: '-' }}</td>
                    <td>{{ $item->route ?: '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($prescription->instructions)
        <h2>Instructions générales</h2>
        <div class="panel">{{ $prescription->instructions }}</div>
    @endif

    {{--
        Cachet de l'établissement, signature et cachet du prescripteur.

        Les trois images viennent de l'application hôte, qui les administre :
        le module se contente de les placer ({@see Keneya\Dme\Dme::signaturesUsing}).
        Là où rien n'est déposé, un cadre vide prend la place, pour que le
        praticien signe à la main sur le document imprimé.
    --}}
    <table class="sign">
        <tr>
            <td width="42%">
                <span class="sign-title">Cachet de l'établissement</span>
                @if ($signatures['facilityStamp'])
                    <div class="sign-marks"><img src="{{ $signatures['facilityStamp'] }}" alt=""></div>
                @else
                    <div class="sign-frame"></div>
                @endif
            </td>
            <td width="16%" class="small muted">
                Ordonnance {{ $prescription->statusLabel() }}
                @if ($prescription->validated_at)
                    <br>le {{ $prescription->validated_at->format('d/m/Y à H:i') }}
                @endif
            </td>
            <td width="42%" style="text-align: center">
                <span class="sign-title">Signature et cachet du prescripteur</span>
                @if ($signatures['doctorSignature'] || $signatures['doctorStamp'])
                    <div class="sign-marks">
                        @if ($signatures['doctorSignature'])
                            <img src="{{ $signatures['doctorSignature'] }}" alt="">
                        @endif
                        @if ($signatures['doctorStamp'])
                            <img src="{{ $signatures['doctorStamp'] }}" alt="">
                        @endif
                    </div>
                @else
                    <div class="sign-frame"></div>
                @endif
                <div class="sign-rule">
                    <strong>{{ $prescription->doctor?->displayName() ?? '-' }}</strong>
                    <span class="small muted">{{ $prescription->doctor?->speciality ?: 'Médecin prescripteur' }}</span>
                </div>
            </td>
        </tr>
    </table>
@endsection
