{{--
    Gabarit commun des documents PDF (§47).

    Chaque document porte l'établissement, le patient, la date, l'auteur,
    sa référence métier et un QR code permettant d'en vérifier l'origine.
    Le style est en ligne : DomPDF ne charge pas les feuilles externes.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Document' }} - {{ $reference }}</title>
    <style>
        @page { margin: 22mm 16mm 20mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1e293b; line-height: 1.5; }
        .header { border-bottom: 2px solid #2563eb; padding-bottom: 8px; margin-bottom: 14px; }
        .header td { vertical-align: top; }
        .facility-logo { height: 34px; width: auto; margin-bottom: 4px; }
        .facility-name { font-size: 15px; font-weight: bold; color: #1e3a8a; }
        .facility-meta { font-size: 9px; color: #64748b; }
        .doc-type { font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        .reference { font-family: DejaVu Sans Mono, monospace; font-size: 9.5px; color: #475569; }
        h2 { font-size: 11px; text-transform: uppercase; letter-spacing: 0.4px;
             color: #475569; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px;
             margin: 14px 0 6px; }
        .panel { background: #f8fafc; border: 1px solid #e2e8f0; padding: 8px 10px; margin-bottom: 10px; }
        .alert { background: #fef2f2; border-left: 3px solid #dc2626; padding: 7px 10px; margin-bottom: 10px; }
        .alert-title { font-weight: bold; color: #991b1b; font-size: 9.5px; text-transform: uppercase; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.data th { background: #f1f5f9; text-align: left; padding: 5px 7px; font-size: 9px;
                        text-transform: uppercase; color: #475569; border-bottom: 1px solid #cbd5e1; }
        table.data td { padding: 5px 7px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .muted { color: #64748b; }
        .small { font-size: 9px; }
        .strong { font-weight: bold; }
        .footer { position: fixed; bottom: -14mm; left: 0; right: 0;
                  font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 4px; }
        .signature { margin-top: 26px; }
        .signature-line { border-top: 1px solid #94a3b8; width: 190px; padding-top: 3px; font-size: 9px; }
        /* Cachets et signature. Chaque element manquant laisse son espace
           vide : un document doit s'imprimer pour un praticien qui n'a rien
           depose, et pour un etablissement sans cachet. */
        .sign { width: 100%; margin-top: 22px; }
        .sign td { vertical-align: top; }
        .sign-title { font-size: 9px; font-weight: bold; color: #1e3a8a;
                      text-transform: uppercase; letter-spacing: 0.4px; }
        .sign-frame { margin-top: 5px; height: 70px; border: 1px dashed #cbd5e1; }
        .sign-marks { height: 58px; margin-top: 5px; }
        .sign-marks img { max-height: 54px; max-width: 150px; }
        .sign-rule { border-top: 1px solid #1e3a8a; padding-top: 4px; }
        .sign-rule strong { display: block; font-size: 10.5px; }
        .qr { text-align: right; }
        .qr img { width: 72px; height: 72px; }
    </style>
</head>
<body>

<table class="header" width="100%">
    <tr>
        <td width="55%">
            {{-- Le logo est facultatif : l'hôte le fournit ou non, et l'entête
                 tient debout sans lui. --}}
            @if (! empty($facility['logo']))
                <img class="facility-logo" src="{{ $facility['logo'] }}" alt="">
            @endif
            <div class="facility-name">{{ $facility['name'] }}</div>
            <div class="facility-meta">
                {{ $facility['address'] }}<br>
                Tél. {{ $facility['phone'] }} - {{ $facility['email'] }}
            </div>
        </td>
        <td width="30%">
            <div class="doc-type">{{ $title ?? 'Document médical' }}</div>
            <div class="reference">Réf. {{ $reference }}</div>
            <div class="facility-meta">Édité le {{ $generatedAt->translatedFormat('d/m/Y à H:i') }}</div>
        </td>
        <td width="15%" class="qr">
            <img src="{{ $qrCode }}" alt="">
        </td>
    </tr>
</table>

@isset($patient)
    <div class="panel">
        <table width="100%">
            <tr>
                <td width="50%">
                    <span class="strong">{{ $patient->fullName() }}</span><br>
                    <span class="small muted">
                        Dossier médical {{ $patient->patient_number }}@if ($patient->externalIdentifier()) &middot; Patient {{ $patient->externalIdentifier() }}@endif - {{ $patient->ageLabel() }} - {{ $patient->sexLabel() }}
                    </span>
                </td>
                <td width="50%" class="small muted">
                    @if ($patient->birth_date)
                        Né(e) le {{ $patient->birth_date->format('d/m/Y') }}<br>
                    @endif
                    @if ($patient->blood_group) Groupe sanguin : {{ $patient->blood_group }}<br> @endif
                    @if ($patient->phone) Tél. {{ $patient->phone }} @endif
                </td>
            </tr>
        </table>
    </div>
@endisset

@yield('content')

{{-- Rendu HTML destiné à l'impression : la boîte d'impression s'ouvre d'elle
     même, comme le faisait l'écran imprimable qu'il remplace. DomPDF ignore
     les scripts, le PDF n'en est pas affecté. --}}
@if (! empty($autoPrint))
    <script>window.addEventListener('load', function () { window.print(); });</script>
@endif

<div class="footer">
    Document généré par Keneya-DME - {{ $facility['name'] }} - Réf. {{ $reference }}.
    {{-- La mention de démonstration reste en place tant que l'exploitant ne
         l'a pas levée : une ordonnance réelle ne doit pas la porter, mais un
         jeu d'essai pris pour un vrai document serait plus grave encore. --}}
    @if (config('dme.documents.demo_notice', true))
        Données de démonstration fictives.
    @endif
</div>

</body>
</html>
