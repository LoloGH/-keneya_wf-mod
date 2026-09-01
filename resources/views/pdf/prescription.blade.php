{{-- Ordonnance au format A4, rendue par dompdf.

     Rien n'y est laisse au navigateur : dompdf ne connait ni flexbox ni
     grid, la mise en page repose donc sur des tableaux et des marges. --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Ordonnance {{ $prescription->patient->patient_code }}</title>
    <style>
        @page { margin: 18mm 16mm; }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #14191d;
            margin: 0;
        }

        /* En-tete : l'etablissement domine, le type de document se lit sous
           lui, et le numero de dossier reste a droite ou l'oeil le cherche. */
        /* `width: 100%` est indispensable : sans elle dompdf ajuste le tableau
           a son contenu, le filet de l'en-tete s'arrete au milieu de la page et
           le numero de dossier ne va pas se ranger a droite. */
        .head { width: 100%; border-bottom: 2px solid #10557f; padding-bottom: 10px; margin-bottom: 16px; }
        .head td { vertical-align: bottom; }
        /* Le monogramme signe le document sans prendre le pas sur le nom de
           l'etablissement, qui reste ce que le patient et le pharmacien lisent
           en premier. dompdf lit l'image sur le disque : un chemin, pas une URL. */
        .head .logo { width: 46px; }
        .head .logo img { width: 40px; height: 32px; }
        .head h1 { margin: 0; font-size: 16px; color: #0b3a58; letter-spacing: -.2px; }
        .head .type { margin: 3px 0 0; font-size: 10px; text-transform: uppercase;
                      letter-spacing: 1.4px; color: #4a555d; }
        /* Coordonnees de l'etablissement, sous son nom : lues par le patient
           qui veut rappeler, et par la pharmacie qui veut verifier. */
        .head .coord { margin: 2px 0 0; font-size: 8.5px; color: #4a555d; }
        .head .dossier { text-align: right; font-size: 10px; color: #4a555d; }
        .head .dossier strong { display: block; font-size: 14px; color: #14191d; letter-spacing: .5px; }

        .meta { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .meta td { padding: 3px 12px 3px 0; vertical-align: top; }
        .meta .label { color: #6f7a83; font-size: 9px; text-transform: uppercase; letter-spacing: .8px; }

        /* Le tableau des lignes : c'est le corps du document. Chaque ligne
           porte son rang, comme a la lecture au comptoir. */
        .lignes { width: 100%; border-collapse: collapse; }
        .lignes thead th {
            font-size: 9px; text-transform: uppercase; letter-spacing: .8px;
            color: #6f7a83; text-align: left; font-weight: normal;
            border-bottom: 1px solid #10557f; padding: 0 6px 5px;
        }
        .lignes td { padding: 8px 6px; border-bottom: 1px solid #e6ebee; vertical-align: top; }
        .lignes .rang { width: 22px; color: #10557f; font-weight: bold; text-align: center; }
        .lignes .medicament { font-weight: bold; }
        .lignes .duree { width: 90px; white-space: nowrap; }
        .lignes .vide { color: #97a2aa; }

        /* Les lignes reprises d'une ordonnance ecrite avant la v3.2.6 : un
           seul bloc de texte, sans colonnes a remplir. */
        .lignes .libre { font-weight: normal; }

        .sign { width: 100%; margin-top: 40px; }
        .sign td { vertical-align: top; }
        .sign .cachet { width: 45%; font-size: 9px; color: #6f7a83; }
        .sign .cachet .cadre { border: 1px dashed #d3dbe0; height: 62px; margin-top: 4px; }
        .sign .medecin { width: 45%; text-align: right; }
        .sign .medecin .trait { border-top: 1px solid #4a555d; padding-top: 5px; margin-top: 8px; }
        /* Les images gardent une hauteur bornee : une signature scannee de
           travers ne doit pas pousser le pied de page hors de la feuille. */
        .sign img { max-height: 52px; max-width: 150px; }
        .sign .medecin .paraphe { height: 56px; text-align: right; }
        .sign .medecin strong { display: block; }
        .sign .medecin span { font-size: 9px; color: #6f7a83; }

        .foot {
            position: fixed; bottom: -8mm; left: 0; right: 0;
            font-size: 8px; color: #97a2aa; text-align: center;
        }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td class="logo"><img src="{{ public_path('images/keneya-icone-impression.png') }}" alt=""></td>
            <td>
                <h1>{{ $hospitalName }}</h1>
                <p class="type">Ordonnance medicale</p>
                {{-- Chaque coordonnee n'apparait que si elle est renseignee
                     dans /admin : rien n'est code en dur ici, et une ligne
                     laissee vide ne laisse pas de tiret orphelin. --}}
                @if (filled($hospitalAddress) || filled($hospitalPhone) || filled($hospitalEmail))
                    <p class="coord">
                        {{ collect([$hospitalAddress, $hospitalPhone, $hospitalEmail])
                            ->filter(fn ($valeur) => filled($valeur))
                            ->join(' · ') }}
                    </p>
                @endif
            </td>
            <td class="dossier">
                Dossier
                <strong>{{ $prescription->patient->patient_code }}</strong>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td class="label">Patient</td>
            <td class="label">Age et sexe</td>
            <td class="label">Service</td>
            <td class="label">Date</td>
        </tr>
        <tr>
            <td><strong>{{ $prescription->patient->name }}</strong></td>
            <td>{{ $prescription->patient->age }} ans, {{ $prescription->patient->gender }}</td>
            <td>{{ $prescription->visit?->service?->name ?? 'Non precise' }}</td>
            <td>{{ $prescription->created_at->format('d/m/Y') }}</td>
        </tr>
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
                    <td>{{ $ligne['posologie'] ?: '' }}</td>
                    <td class="duree">{{ $ligne['duree'] ?: '' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="vide">Aucune ligne.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Signature et tampons. Chaque element manquant laisse son espace vide :
         une ordonnance doit s'imprimer meme pour un medecin qui n'a rien
         depose, et meme si l'etablissement n'a pas encore de tampon. --}}
    <table class="sign">
        <tr>
            <td class="cachet">
                Cachet de l'etablissement
                @if ($hospitalStamp)
                    <div><img src="{{ $hospitalStamp }}" alt=""></div>
                @else
                    <div class="cadre"></div>
                @endif
            </td>
            <td></td>
            <td class="medecin">
                <div class="paraphe">
                    @if ($doctorSignature)
                        <img src="{{ $doctorSignature }}" alt="">
                    @endif
                    @if ($doctorStamp)
                        <img src="{{ $doctorStamp }}" alt="">
                    @endif
                </div>
                <div class="trait">
                    <strong>{{ $prescription->doctor->name() }}</strong>
                    <span>Signature du medecin</span>
                </div>
            </td>
        </tr>
    </table>

    <div class="foot">
        {{ $productName }} &middot; {{ $hospitalName }} &middot;
        Ordonnance {{ $prescription->patient->patient_code }} du {{ $prescription->created_at->format('d/m/Y') }} a {{ $prescription->created_at->format('H:i') }}
    </div>
</body>
</html>
