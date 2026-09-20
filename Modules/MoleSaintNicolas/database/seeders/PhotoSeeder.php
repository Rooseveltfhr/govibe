<?php

namespace Database\Seeders;

use App\Models\Photo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Photos de la galerie / diaporama de l'accueil.
 *
 * Historique : ce seeder a d'abord posé 5 illustrations générées (pas des
 * photos réelles) pour ne pas laisser la galerie vide le temps que le client
 * fournisse de vraies photos. Le client a depuis fourni 6 vraies photos —
 * elles remplacent maintenant les illustrations, qui sont supprimées si elles
 * sont encore là (catégorie "demo"). Idempotent au-delà de cette transition
 * ponctuelle : ne repose jamais rien tant qu'il reste au moins une photo,
 * pour ne jamais écraser un ajout ou une suppression faits depuis l'admin.
 */
class PhotoSeeder extends Seeder
{
    public function run(): void
    {
        $demoPhotos = Photo::where('category', 'demo')->get();
        foreach ($demoPhotos as $photo) {
            Storage::disk('public')->delete($photo->path);
            $photo->delete();
        }

        if (Photo::count() > 0) {
            return;
        }

        $sourceDir = __DIR__.'/real-photos';
        $photos = [
            '1-rue-de-nuit.jpg' => ['title' => 'Rue de Môle-Saint-Nicolas, de nuit', 'category' => 'paysage'],
            '2-plage.jpg' => ['title' => 'Plage de Môle-Saint-Nicolas', 'category' => 'plage'],
            '3-phare.jpg' => ['title' => 'Phare', 'category' => 'patrimoine'],
            '4-piscine.jpg' => ['title' => 'Piscine en bord de mer', 'category' => 'hebergement'],
            '5-vue-aerienne.jpg' => ['title' => 'Vue aérienne — piscine et villas', 'category' => 'hebergement'],
            '6-coucher-de-soleil.jpg' => ['title' => 'Coucher de soleil sur les collines', 'category' => 'paysage'],
        ];

        foreach ($photos as $filename => $meta) {
            $path = 'galerie/'.$filename;
            Storage::disk('public')->put($path, file_get_contents("$sourceDir/$filename"));

            Photo::create([
                'title' => $meta['title'],
                'category' => $meta['category'],
                'path' => $path,
                'content_status' => 'submitted',
                'source_note' => 'Photo fournie directement par le client (Roosevelt).',
            ]);
        }
    }
}
