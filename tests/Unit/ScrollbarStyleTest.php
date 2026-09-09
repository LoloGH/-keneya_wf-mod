<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Les barres de defilement masquees.
 *
 * Ce que ces tests protegent n'est pas une preference esthetique : c'est le
 * couple. Une barre masquee sur un conteneur qui ne defile plus rend son
 * contenu inatteignable **et sans le moindre indice**, puisque la barre qui
 * l'aurait signale ne s'affiche plus. Le defaut serait alors invisible en
 * relecture de code comme a l'ecran.
 */
class ScrollbarStyleTest extends TestCase
{
    /** Conteneurs dont la barre est masquee et qui doivent continuer a defiler. */
    private const CONTENEURS = ['.tabnav', '.tabnav--open', '.bell__panel'];

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
        $this->assertStringContainsString('::-webkit-scrollbar', $css);
    }

    public function test_masquer_la_barre_ne_supprime_pas_le_defilement(): void
    {
        $css = $this->css();

        foreach (self::CONTENEURS as $conteneur) {
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($conteneur, '/').'\s*\{[^}]*overflow-y:\s*auto/',
                $css,
                $conteneur.' n\'a plus de defilement alors que sa barre est masquee : '
                    .'son contenu est devenu inatteignable, et rien ne le signale.'
            );
        }
    }

    public function test_les_tableaux_larges_gardent_leur_barre(): void
    {
        $css = $this->css();

        // Exclusion deliberee. Un defilement vertical se devine : on fait
        // tourner la molette. Un defilement horizontal, non : la barre y est
        // souvent le seul indice qu'une colonne continue a droite.
        $this->assertDoesNotMatchRegularExpression(
            '/\.table-wrap[^{]*::-webkit-scrollbar/',
            $css,
            'La barre horizontale des tableaux doit rester visible.'
        );
    }
}
