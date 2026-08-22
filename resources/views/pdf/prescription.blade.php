<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Ordonnance {{ $prescription->patient->patient_code }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1c2226; margin: 0; padding: 32px; }
        .head { border-bottom: 2px solid #0f5c8c; padding-bottom: 12px; margin-bottom: 20px; }
        .head h1 { margin: 0; font-size: 18px; color: #0a3f61; }
        .head p { margin: 2px 0 0; color: #454f56; }
        .meta { width: 100%; margin-bottom: 22px; border-collapse: collapse; }
        .meta td { padding: 4px 0; vertical-align: top; }
        .meta .label { color: #6d7880; width: 130px; text-transform: uppercase; font-size: 10px; letter-spacing: .04em; }
        .content { border: 1px solid #ccd4d9; border-radius: 6px; padding: 16px; min-height: 260px; white-space: pre-wrap; line-height: 1.6; }
        .sign { margin-top: 36px; text-align: right; }
        .sign .line { display: inline-block; border-top: 1px solid #454f56; padding-top: 6px; min-width: 220px; }
        .foot { margin-top: 28px; font-size: 10px; color: #6d7880; text-align: center; }
    </style>
</head>
<body>
    <div class="head">
        <h1>{{ $hospitalName }}</h1>
        <p>{{ $productName }} — Ordonnance</p>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Patient</td>
            <td><strong>{{ $prescription->patient->name }}</strong> ({{ $prescription->patient->patient_code }})</td>
        </tr>
        <tr>
            <td class="label">Age / Sexe</td>
            <td>{{ $prescription->patient->age }} ans — {{ $prescription->patient->gender }}</td>
        </tr>
        <tr>
            <td class="label">Service</td>
            <td>{{ $prescription->visit?->service?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Date</td>
            <td>{{ $prescription->created_at->format('d/m/Y H:i') }}</td>
        </tr>
    </table>

    <div class="content">{{ $prescription->content }}</div>

    <div class="sign">
        <div class="line">{{ $prescription->doctor->name() }}</div>
    </div>

    <div class="foot">
        Document genere par {{ $productName }} — {{ $hospitalName }}
    </div>
</body>
</html>
