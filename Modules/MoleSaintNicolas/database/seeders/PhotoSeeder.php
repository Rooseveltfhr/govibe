<?php

namespace Database\Seeders;

use App\Models\Photo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Illustrations de démonstration (générées, pas des photos réelles) pour ne
 * pas laisser la galerie/le diaporama de l'accueil vides en attendant que le
 * client ajoute de vraies photos via /admin/galerie. Idempotent : ne tourne
 * que si la galerie est encore complètement vide, pour ne jamais revenir
 * après un ajout ou une suppression faits depuis l'admin.
 */
class PhotoSeeder extends Seeder
{
    public function run(): void
    {
        if (Photo::count() > 0) {
            return;
        }

        $sourceDir = __DIR__.'/demo-photos';
        $photos = [
            'demo-1-coucher-de-soleil.jpg' => 'Illustration — coucher de soleil sur la mer',
            'demo-2-palmiers.jpg' => 'Illustration — palmiers',
            'demo-3-collines.jpg' => 'Illustration — collines',
            'demo-4-voilier.jpg' => 'Illustration — voilier',
            'demo-5-fort.jpg' => 'Illustration — fort',
        ];

        foreach ($photos as $filename => $title) {
            $path = 'galerie/'.$filename;
            Storage::disk('public')->put($path, file_get_contents("$sourceDir/$filename"));

            Photo::create([
                'title' => $title,
                'category' => 'demo',
                'path' => $path,
                'content_status' => 'needs_review',
                'source_note' => 'Illustration de démonstration générée (pas une photo réelle) — à remplacer par de vraies photos via Admin → Galerie.',
            ]);
        }
    }
}
