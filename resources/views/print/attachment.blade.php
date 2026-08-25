<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $attachment->original_name }} — {{ $hospitalName }}</title>
    <style>
        body { margin: 0; padding: 16px; font-family: system-ui, sans-serif; background: #eef2f5; color: #10171c; }
        .sheet { max-width: 900px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; }
        .sheet__head { border-bottom: 2px solid #0f5c8c; padding-bottom: 10px; margin-bottom: 16px; }
        .sheet__head h1 { margin: 0; font-size: 1.1rem; color: #0a3f61; }
        .sheet__head p { margin: 2px 0 0; font-size: .85rem; color: #5a656d; }
        .sheet img { display: block; max-width: 100%; height: auto; margin: 0 auto; }
        .sheet embed, .sheet iframe { width: 100%; height: 78vh; border: 1px solid #ccd4d9; }
        .actions { max-width: 900px; margin: 14px auto 0; display: flex; gap: 8px; }
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
            .sheet embed, .sheet iframe { height: auto; min-height: 90vh; border: 0; }
            @page { margin: 10mm; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="sheet__head">
            <h1>{{ $hospitalName }}</h1>
            <p>
                {{ $attachment->original_name }} —
                dossier {{ $attachment->patient->patient_code }} —
                {{ $attachment->created_at->format('d/m/Y H:i') }}
            </p>
        </div>

        @if ($attachment->isImage())
            <img src="{{ $downloadUrl }}" alt="{{ $attachment->original_name }}">
        @else
            {{-- Un PDF s'imprime depuis le visualiseur du navigateur. --}}
            <embed src="{{ $downloadUrl }}" type="{{ $attachment->mime_type }}">
        @endif
    </div>

    <div class="actions">
        <button type="button" onclick="window.print()">Imprimer</button>
        <a class="secondary" href="{{ $downloadUrl }}">Telecharger</a>
    </div>
</body>
</html>
