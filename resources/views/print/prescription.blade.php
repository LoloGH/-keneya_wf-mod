{{-- Rendu HTML imprimable d'une ordonnance, cohérent avec le PDF dompdf. --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ordonnance {{ $prescription->patient->patient_code }} — {{ $hospitalName }}</title>
    <style>
        body { margin: 0; padding: 20px; font-family: system-ui, sans-serif; background: #eef2f5; color: #1c2226; }
        .sheet { max-width: 760px; margin: 0 auto; background: #fff; padding: 32px; border-radius: 8px; }
        .head { border-bottom: 2px solid #0f5c8c; padding-bottom: 12px; margin-bottom: 20px; }
        .head h1 { margin: 0; font-size: 1.15rem; color: #0a3f61; }
        .head p { margin: 2px 0 0; color: #454f56; font-size: .9rem; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 22px; font-size: .95rem; }
        .meta td { padding: 4px 0; vertical-align: top; }
        .meta .label { color: #6d7880; width: 150px; text-transform: uppercase; font-size: .72rem; letter-spacing: .04em; }
        .content { border: 1px solid #ccd4d9; border-radius: 6px; padding: 18px; min-height: 260px;
                   white-space: pre-wrap; line-height: 1.65; }
        .sign { margin-top: 40px; text-align: right; }
        .sign .line { display: inline-block; border-top: 1px solid #454f56; padding-top: 6px; min-width: 240px; }
        .foot { margin-top: 26px; font-size: .72rem; color: #6d7880; text-align: center; }
        .actions { max-width: 760px; margin: 14px auto 0; display: flex; gap: 8px; }
        .actions a, .actions button {
            min-height: 44px; padding: .55rem 1.1rem; font: inherit; font-weight: 600;
            border-radius: 8px; border: 1px solid #0f5c8c; cursor: pointer; text-decoration: none;
            background: #0f5c8c; color: #fff; display: inline-flex; align-items: center;
        }
        .actions .secondary { background: #fff; color: #0f5c8c; }

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
            <h1>{{ $hospitalName }}</h1>
            <p>{{ config('keneya.name') }} — Ordonnance</p>
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

        <div class="content">{{ $prescription->content }}</div>

        <div class="sign"><div class="line">{{ $prescription->doctor->name() }}</div></div>

        <div class="foot">Document genere par {{ config('keneya.name') }} — {{ $hospitalName }}</div>
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
