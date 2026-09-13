@extends('dme::pdf._layout', ['title' => 'Fiche patient'])

@section('content')
    @if ($patient->criticalAllergies()->isNotEmpty())
        <div class="alert">
            <div class="alert-title">Allergies, vigilance</div>
            @foreach ($patient->criticalAllergies() as $allergy)
                <div class="small">
                    {{ $allergy->allergen }} - {{ $allergy->severityLabel() }}
                    @if ($allergy->reaction) ({{ $allergy->reaction }}) @endif
                </div>
            @endforeach
        </div>
    @endif

    <h2>Identité</h2>
    <table class="data">
        <tbody>
            @foreach ([
                'Nom complet' => $patient->fullName(),
                'Numéro de dossier' => $patient->patient_number,
                'Date de naissance' => $patient->birth_date?->format('d/m/Y').' ('.$patient->ageLabel().')',
                'Sexe' => $patient->sexLabel(),
                'Lieu de naissance' => $patient->birth_place ?: '-',
                'Nationalité' => $patient->nationality ?: '-',
                'Groupe sanguin' => $patient->blood_group ?: 'Inconnu',
                'Médecin traitant' => $patient->attendingDoctor?->displayName() ?? 'Non attribué',
            ] as $label => $value)
                <tr>
                    <td width="30%" class="muted">{{ $label }}</td>
                    <td class="strong">{{ $value }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Coordonnées</h2>
    <table class="data">
        <tbody>
            @foreach ([
                'Téléphone' => $patient->phone ?: '-',
                'Adresse e-mail' => $patient->email ?: '-',
                'Adresse' => trim(($patient->address ?: '').' '.($patient->city ?: '').' '.($patient->country ?: '')) ?: '-',
            ] as $label => $value)
                <tr>
                    <td width="30%" class="muted">{{ $label }}</td>
                    <td>{{ $value }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Pathologies chroniques</h2>
    @if ($patient->chronicConditions->isEmpty())
        <div class="muted small">Aucune pathologie chronique documentée.</div>
    @else
        <table class="data">
            <thead><tr><th width="50%">Pathologie</th><th width="15%">Code</th><th width="20%">Depuis</th><th width="15%">Statut</th></tr></thead>
            <tbody>
                @foreach ($patient->chronicConditions as $condition)
                    <tr>
                        <td class="strong">{{ $condition->label }}</td>
                        <td>{{ $condition->code ?: '-' }}</td>
                        <td>{{ $condition->diagnosed_on?->format('m/Y') ?: '-' }}</td>
                        <td>{{ $condition->statusLabel() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Allergies</h2>
    @if ($patient->allergies->isEmpty())
        <div class="muted small">Aucune allergie documentée.</div>
    @else
        <table class="data">
            <thead><tr><th width="30%">Allergène</th><th width="35%">Réaction</th><th width="20%">Gravité</th><th width="15%">Statut</th></tr></thead>
            <tbody>
                @foreach ($patient->allergies as $allergy)
                    <tr>
                        <td class="strong">{{ $allergy->allergen }}</td>
                        <td>{{ $allergy->reaction ?: '-' }}</td>
                        <td>{{ $allergy->severityLabel() }}</td>
                        <td>{{ $allergy->status === 'active' ? 'Active' : 'Inactive' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Traitements habituels</h2>
    @if ($patient->medications->isEmpty())
        <div class="muted small">Aucun traitement habituel documenté.</div>
    @else
        <table class="data">
            <thead><tr><th width="35%">Médicament</th><th width="20%">Dosage</th><th width="30%">Fréquence</th><th width="15%">Statut</th></tr></thead>
            <tbody>
                @foreach ($patient->medications as $medication)
                    <tr>
                        <td class="strong">{{ $medication->name }}</td>
                        <td>{{ $medication->dosage ?: '-' }}</td>
                        <td>{{ $medication->frequency ?: '-' }}</td>
                        <td>{{ $medication->statusLabel() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
