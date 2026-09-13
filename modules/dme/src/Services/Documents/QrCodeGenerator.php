<?php

declare(strict_types=1);

namespace Keneya\Dme\Services\Documents;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Génération des QR codes apposés sur les documents PDF (§47).
 *
 * Le QR code encode l'URL de vérification du document : il permet à un
 * pharmacien ou à un confrère de confirmer l'origine d'une ordonnance
 * imprimée. Le rendu est produit en SVG, sans dépendance à l'extension
 * imagick, puis converti en data URI pour être embarqué dans le PDF.
 */
class QrCodeGenerator
{
    public function svg(string $content, int $size = 160): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd(),
        ));

        return $writer->writeString($content);
    }

    /**
     * Data URI directement utilisable dans un <img src="..."> du PDF.
     */
    public function dataUri(string $content, int $size = 160): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->svg($content, $size));
    }
}
