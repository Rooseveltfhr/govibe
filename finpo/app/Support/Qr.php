<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Throwable;

class Qr
{
    /**
     * Rend un QR code en SVG inline (data URI) — généré localement, sans
     * dépendre d'un service externe. Retourne une URL d'image de secours
     * si la génération échoue.
     */
    public static function svgDataUri(string $payload, int $size = 300): string
    {
        try {
            $renderer = new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd());
            $svg = (new Writer($renderer))->writeString($payload);

            return 'data:image/svg+xml;base64,'.base64_encode($svg);
        } catch (Throwable) {
            return 'https://api.qrserver.com/v1/create-qr-code/?size='.$size.'x'.$size
                .'&data='.urlencode($payload);
        }
    }
}
