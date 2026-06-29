<?php

namespace Modules\Tagtoa\App\Support;

/**
 * TAGTOA — génération de QR code. SVG local via simple-qrcode si dispo,
 * sinon image de secours (api.qrserver.com). Tolérant : ne casse jamais.
 */
class Qr
{
    /** QR en SVG inline (markup). Vide si indisponible. */
    public static function svg(string $text, int $size = 220): string
    {
        $cls = '\SimpleSoftwareIO\QrCode\Facades\QrCode';
        if (class_exists($cls)) {
            try {
                return (string) $cls::format('svg')->size($size)->margin(1)->errorCorrection('M')->generate($text);
            } catch (\Throwable $e) {
                // tombe sur l'image de secours
            }
        }

        return '';
    }

    /** URL d'une image QR de secours (rendu par le navigateur du client). */
    public static function imgUrl(string $text, int $size = 220): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size='.$size.'x'.$size.'&margin=4&data='.rawurlencode($text);
    }
}
