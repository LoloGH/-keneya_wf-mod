<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Les barres de defilement : ou elles s'effacent, ou elles doivent rester.
 *
 * Le partage n'est pas esthetique, il tient a ce que la barre apprend.
 *
 * Sur la page entiere, elle n'apprend rien : on sait qu'une page continue vers
 * le bas, et on fait tourner la molette sans y penser. Elle s'efface donc, et
 * ce que ce fichier verrouille alors est le couple — une barre masquee sur un
 * conteneur qui ne defile plus rend son contenu inatteignable **et sans le
 * moindre indice**, puisque la barre qui l'aurait signale ne s'affiche plus.
 * Le defaut serait invisible en relecture de code comme a l'ecran.
 *
 * Dans un menu, elle apprend quelque chose, et c'est l'inverse qu'il faut
 * proteger. Une colonne de sections plus haute que l'ecran, sans barre, ne dit
 * pas qu'elle continue : rien ne distingue « voila toutes les sections » de
 * « la derniere est sous le bord ». Ces conteneurs-la ont donc retrouve la
 * leur, discrete au repos et coloree au survol.
 */
class ScrollbarStyleTest extends TestCase
{
    /**
     * Menus et panneaux : ils defilent, et cela doit se voir.
     *
     * `.profil__panel` s'y est ajoute avec le survol : le formulaire de mot de
     * passe allonge la carte au-dela du bas d'un ecran de portable.
     */
    private const AVEC_BARRE = ['.tabnav', '.tabnav--open', '.bell__panel', '.profil__panel'];

    private function css(): string
    {
        return (string) file_get_contents(__DIR__.'/../../public/css/app.css');
    }

    public function test_les_deux_familles_de_navigateurs_sont_couvertes(): void
    {
        $css = $this->css();

        // Aucune des deux ecritures n'est facultative : `scrollbar-width` ne
        // dit rien a Chrome, `::-webkit-scrollbar` ne dit rien a Firefox. En
        // oublier une laisse la barre visible sur la moitie des navigateurs,
        // et le defaut ne se voit que sur la machine de quelqu'un d'autre.
        $this->assertStringContainsString('scrollbar-width: none', $css);
        $this->assertStringContainsString('scrollbar-width: thin', $css);
        $this->assertStringContainsString('::-webkit-scrollbar', $css);
    }

    public function test_la_page_masque_sa_barre_sans_perdre_son_defilement(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression(
            '/html,\s*\n\s*body\s*\{[^}]*scrollbar-width:\s*none/',
            $css,
            'La page a repris une barre de defilement pleine hauteur.'
        );

        // Masquer n'est pas supprimer : aucune regle ne doit couper le
        // defilement de la page elle-meme.
        $this->assertDoesNotMatchRegularExpression(
            '/html,\s*\n\s*body\s*\{[^}]*overflow(-y)?:\s*hidden/',
            $css,
            'La page ne defile plus, et plus rien ne le signale.'
        );
    }

    public function test_les_menus_qui_defilent_montrent_leur_barre(): void
    {
        $css = $this->css();

        foreach (self::AVEC_BARRE as $conteneur) {
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($conteneur, '/').'\s*[,{][^}]*overflow-y:\s*auto/',
                $css,
                $conteneur.' ne defile plus.'
            );

            // La barre rendue, et non reprise a zero : un conteneur qui defile
            // et qui figure encore parmi les barres masquees est exactement le
            // cas que ce fichier existe pour empecher.
            $this->assertDoesNotMatchRegularExpression(
                '/'.preg_quote($conteneur, '/').'\s*[,{][^}]*scrollbar-width:\s*none/',
                $css,
                $conteneur.' defile sans barre : rien ne dit qu\'il continue sous le bord.'
            );
        }
    }

    public function test_les_tableaux_larges_gardent_leur_barre(): void
    {
        $css = $this->css();

        // Exclusion deliberee. Un defilement vertical se devine : on fait
        // tourner la molette. Un defilement horizontal, non : la barre y est
        // souvent le seul indice qu'une colonne continue a droite. Elle n'est
        // donc pas non plus conditionnee au survol.
        $this->assertDoesNotMatchRegularExpression(
            '/\.table-wrap[^{]*::-webkit-scrollbar/',
            $css,
            'La barre horizontale des tableaux doit rester visible.'
        );
    }
}
