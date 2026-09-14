<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Le numero de version affiche doit suivre ce que le depot a livre.
 *
 * Le fichier `VERSION` est le seul endroit ou ce numero est ecrit ; il
 * ressort au pied de chaque interface et sur l'ecran de connexion. Un numero
 * faux y est pire que pas de numero du tout : devant un ecran, il repond avec
 * aplomb a la question « quel etat du code ai-je sous les yeux ».
 *
 * Il l'a ete. Le fichier est reste sur 3.3.0 pendant que cinq jalons etaient
 * livres et documentes, jusqu'a la v3.4.2 : tous les postes de l'hopital
 * annoncaient une version vieille de cinq chantiers. Rien ne l'a signale,
 * parce que rien ne regardait ce fichier — le test du pied de page verifiait
 * que l'affichage correspond au fichier, ce qui reste vrai avec un numero
 * faux.
 *
 * C'est ce trou que ce fichier ferme. Les notes de version portent leur
 * numero dans leur nom, `docs/vX.Y.Z-*.md` : le depot dit donc lui-meme
 * jusqu'ou il est alle, et il suffit de comparer.
 */
class VersionTest extends TestCase
{
    private function racine(): string
    {
        return __DIR__.'/../..';
    }

    private function version(): string
    {
        return trim((string) file_get_contents($this->racine().'/VERSION'));
    }

    /**
     * Les numeros portes par les notes de version, du plus ancien au plus
     * recent.
     *
     * Une note peut n'en porter que deux — `v3.4-portail-dossier-medical.md` —
     * auquel cas le troisieme vaut zero, comme le veut la comparaison de
     * versions.
     *
     * @return list<string>
     */
    private function versionsDocumentees(): array
    {
        $numeros = [];

        foreach (glob($this->racine().'/docs/v*.md') ?: [] as $note) {
            if (preg_match('/^v(\d+)\.(\d+)(?:\.(\d+))?-/', basename($note), $m) !== 1) {
                continue;
            }

            $numeros[] = $m[1].'.'.$m[2].'.'.($m[3] ?? '0');
        }

        usort($numeros, 'version_compare');

        return $numeros;
    }

    public function test_le_fichier_version_porte_trois_nombres(): void
    {
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $this->version());
    }

    /**
     * Le depot ne doit jamais documenter plus loin qu'il ne s'annonce.
     *
     * L'egalite n'est pas exigee dans l'autre sens : un correctif peut sortir
     * sans note de version, et le fichier avoir pris de l'avance. Ce qui est
     * interdit, c'est l'inverse — livrer un chantier, en ecrire la note, et
     * laisser les ecrans annoncer l'etat d'avant.
     */
    public function test_la_version_affichee_n_est_pas_en_retard_sur_les_notes(): void
    {
        $documentees = $this->versionsDocumentees();

        $this->assertNotEmpty(
            $documentees,
            'Aucune note de version trouvee dans docs/ : le nommage a change, et ce test ne protege plus rien.'
        );

        $derniere = end($documentees);
        $version = $this->version();

        $this->assertGreaterThanOrEqual(
            0,
            version_compare($version, $derniere),
            "VERSION annonce {$version} alors que docs/ documente jusqu'a {$derniere}. "
                .'Le pied de page de toutes les interfaces affiche donc un numero faux.'
        );
    }
}
