{{-- Sauvegarde : une page de constat, pas un bouton.

     Une sauvegarde declenchee depuis le navigateur ecrirait son archive dans
     le conteneur applicatif — c'est-a-dire a l'endroit meme qu'elle est censee
     proteger, et qui disparait avec lui. Ce que cette page peut faire, en
     revanche, c'est dire ce qui est en jeu. --}}
@php
    $format = function (?int $octets): string {
        if ($octets === null) {
            return 'dossier absent';
        }

        if ($octets < 1024) {
            return $octets.' o';
        }

        $unites = ['Ko', 'Mo', 'Go'];
        $valeur = $octets / 1024;
        $rang = 0;

        while ($valeur >= 1024 && $rang < count($unites) - 1) {
            $valeur /= 1024;
            $rang++;
        }

        return number_format($valeur, $valeur < 10 ? 1 : 0, ',', ' ').' '.$unites[$rang];
    };
@endphp

<div class="pile">

    <x-card title="Ce qui est en jeu" icon="sauvegarde">
        <div class="chiffres">
            <div class="chiffres__bloc">
                <span class="chiffres__valeur mono">{{ number_format($patients, 0, ',', ' ') }}</span>
                <span class="chiffres__libelle">dossiers patients</span>
            </div>
            <div class="chiffres__bloc">
                <span class="chiffres__valeur mono">{{ number_format($visites, 0, ',', ' ') }}</span>
                <span class="chiffres__libelle">passages enregistres</span>
            </div>
            <div class="chiffres__bloc">
                <span class="chiffres__valeur mono">{{ number_format($ordonnances, 0, ',', ' ') }}</span>
                <span class="chiffres__libelle">ordonnances</span>
            </div>
            <div class="chiffres__bloc">
                <span class="chiffres__valeur mono">{{ number_format($piecesJointes, 0, ',', ' ') }}</span>
                <span class="chiffres__libelle">pieces jointes — {{ $format($poidsPieces) }}</span>
            </div>
            {{-- Une absence n'est pas une valeur : le dossier des signatures
                 n'existe qu'au premier depot, et afficher « dossier absent »
                 en gros chiffre a cote de vrais nombres se lit comme une
                 anomalie alors que c'est un etat normal. --}}
            <div class="chiffres__bloc">
                <span class="chiffres__valeur mono">{{ $poidsSignatures === null ? '—' : $format($poidsSignatures) }}</span>
                <span class="chiffres__libelle">
                    signatures et tampons{{ $poidsSignatures === null ? ' — aucun depose a ce jour' : '' }}
                </span>
            </div>
        </div>

        <x-notice title="Trois choses, pas une">
            <p>
                Un export SQL seul ne restaure pas l'application. Les pieces
                jointes et les signatures vivent hors de la base : une
                restauration sans elles rendrait des dossiers patients
                incomplets et des ordonnances amputees du cachet qui les
                authentifie.
            </p>
        </x-notice>
    </x-card>

    <x-card title="Comment sauvegarder" icon="document">
        <p class="hint">
            La sauvegarde tourne cote systeme, sous un compte capable de lire
            les fichiers deposes par le serveur web. Elle produit trois
            archives horodatees et conserve les trente dernieres de chaque
            serie.
        </p>

        <pre class="code-bloc"><code>./scripts/backup.sh /var/sauvegardes/keneya
./scripts/install-backup-cron.sh 02:30</code></pre>

        <x-notice ton="alerte" title="Une sauvegarde non testee n'est pas une sauvegarde">
            Le seul controle qui compte est une restauration reelle sur une
            installation d'essai. Un fichier d'archive present ne prouve pas
            qu'il est lisible.
        </x-notice>
    </x-card>
</div>
