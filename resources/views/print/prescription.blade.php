{{-- Rendu HTML imprimable d'une ordonnance, cohérent avec le PDF dompdf. --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ordonnance {{ $prescription->patient->patient_code }} — {{ $hospitalName }}</title>
    <style>
        body { margin: 0; padding: 20px; font-family: system-ui, sans-serif; background: #eef3f8; color: #1c2226; }
        .sheet { max-width: 760px; margin: 0 auto; background: #fff; padding: 32px; border-radius: 8px; }
        .head { display: flex; align-items: center; gap: 14px;
                border-bottom: 2px solid #12314d; padding-bottom: 12px; margin-bottom: 20px; }
        .head__logo { flex: 0 0 auto; width: 48px; height: auto; }
        .head h1 { margin: 0; font-size: 1.15rem; color: #12314d; }
        .head p { margin: 2px 0 0; color: #454f56; font-size: .9rem; }
        .head__coord { font-size: .78rem; color: #6f7a83; line-height: 1.5; }
        .devise {
            margin-top: 26px; padding-top: 10px; border-top: 1px solid #16806a;
            text-align: center; font-style: italic; font-weight: 700; color: #16806a;
        }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 22px; font-size: .95rem; }
        .meta td { padding: 4px 0; vertical-align: top; }
        .meta .label { color: #6f7a83; width: 150px; text-transform: uppercase; font-size: .72rem; letter-spacing: .05em; }

        /* Le tableau des lignes est le corps du document : chacune porte son
           rang, comme a la lecture au comptoir de la pharmacie. */
        .lignes { width: 100%; border-collapse: collapse; }
        .lignes thead th {
            font-size: .7rem; text-transform: uppercase; letter-spacing: .06em;
            background: #16806a; color: #fff; text-align: left; font-weight: 600;
            padding: 8px;
        }
        .lignes td { padding: 10px 8px; border-bottom: 1px solid #e6ebee; vertical-align: top; }
        .lignes .rang { width: 28px; color: #16806a; font-weight: 700; text-align: center; }
        .lignes .medicament { font-weight: 700; }
        .lignes .duree { width: 110px; white-space: nowrap; }
        .lignes .vide { color: #97a2aa; }

        .sign { display: flex; justify-content: space-between; gap: 24px; margin-top: 44px; }
        .sign .cachet { font-size: .72rem; color: #6f7a83; }
        .sign .cachet .cadre { border: 1px dashed #d3dbe0; height: 70px; width: 190px; margin-top: 4px; }
        .sign .medecin { align-self: flex-end; text-align: right; }
        .sign .medecin .trait { border-top: 1px solid #4a555d; padding-top: 6px; min-width: 220px; }
        .sign .medecin strong { display: block; }
        .sign .medecin span { font-size: .72rem; color: #6f7a83; }
        .foot { margin-top: 26px; font-size: .72rem; color: #6d7880; text-align: center; }
        .actions { max-width: 760px; margin: 14px auto 0; display: flex; gap: 8px; }
        .actions a, .actions button {
            min-height: 44px; padding: .55rem 1.1rem; font: inherit; font-weight: 600;
            border-radius: 8px; border: 1px solid #12314d; cursor: pointer; text-decoration: none;
            background: #12314d; color: #fff; display: inline-flex; align-items: center;
        }
        .actions .secondary { background: #fff; color: #12314d; }

        @media print {
            body { background: #fff; padding: 0; }
            .actions { display: none !important; }
            .sheet { max-width: none; padding: 0; border-radius: 0; }
            @page { margin: 14mm; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="head">
            <img class="head__logo" src="{{ asset('images/keneya-icone-impression.png') }}"
                 alt="" width="200" height="158">
            <div>
                <h1>{{ $hospitalName }}</h1>
                <p>Ordonnance medicale</p>
                @php
                    $coordonnees = collect([$hospitalAddress, $hospitalPhone, $hospitalEmail, $hospitalWebsite, $hospitalHours])
                        ->filter(fn ($valeur) => filled($valeur));
                @endphp
                @if ($coordonnees->isNotEmpty())
                    <p class="head__coord">{{ $coordonnees->join(' · ') }}</p>
                @endif
            </div>
        </div>

        <table class="meta">
            <tr><td class="label">Patient</td>
                <td><strong>{{ $prescription->patient->name }}</strong> ({{ $prescription->patient->patient_code }})</td></tr>
            <tr><td class="label">Age / Sexe</td>
                <td>{{ $prescription->patient->age }} ans — {{ $prescription->patient->gender }}</td></tr>
            <tr><td class="label">Service</td>
                <td>{{ $prescription->visit?->service?->name ?? '—' }}</td></tr>
            <tr><td class="label">Date</td>
                <td>{{ $prescription->created_at->format('d/m/Y H:i') }}</td></tr>
        </table>

        @php $lignes = $prescription->lignes(); @endphp

        <table class="lignes">
            <thead>
                <tr>
                    <th class="rang">N&deg;</th>
                    <th>Medicament</th>
                    <th>Posologie</th>
                    <th class="duree">Duree</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lignes as $rang => $ligne)
                    <tr>
                        <td class="rang">{{ $rang + 1 }}</td>
                        <td class="medicament">{{ $ligne['medicament'] }}</td>
                        <td>{{ $ligne['posologie'] }}</td>
                        <td class="duree">{{ $ligne['duree'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="vide">Aucune ligne.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="sign">
            <div class="cachet">
                Cachet de l'etablissement
                <div class="cadre"></div>
            </div>
            <div class="medecin">
                <div class="trait">
                    <strong>{{ $prescription->doctor->name() }}</strong>
                    <span>Signature du medecin</span>
                </div>
            </div>
        </div>

        @if (filled($hospitalMotto))
            <div class="devise">{{ $hospitalMotto }}</div>
        @endif

        <div class="foot">
            {{ config('keneya.name') }} &middot; {{ $hospitalName }} &middot;
            Ordonnance {{ $prescription->patient->patient_code }}
            du {{ $prescription->created_at->format('d/m/Y') }} a {{ $prescription->created_at->format('H:i') }}
        </div>
    </div>

    {{-- $pdfUrl reste nul pour les roles qui n'ont pas la route PDF : la vue
         imprimable se suffit alors a elle-meme. --}}
    @php $pdfUrl ??= route('service.prescription.pdf', $prescription); @endphp

    <div class="actions">
        <button type="button" onclick="window.print()">Imprimer</button>
        @if ($pdfUrl)
            <a class="secondary" href="{{ $pdfUrl }}">Telecharger en PDF</a>
        @endif
    </div>
</body>
</html>
