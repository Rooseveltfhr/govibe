<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PhotoController extends Controller
{
    public function index()
    {
        $photos = Photo::orderByDesc('created_at')->get();

        return view('admin.galerie.index', compact('photos'));
    }

    public function create()
    {
        return view('admin.galerie.form');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            // Taille et types limités : hébergement mutualisé à quota de disque
            // restreint (voir docs/molesaintnicolas §12).
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'content_status' => ['required', 'in:verified,submitted,needs_review'],
        ]);

        // store() génère un nom de fichier aléatoire : jamais le nom original
        // envoyé par le navigateur, pour éviter tout risque de traversée de
        // chemin ou d'écrasement d'un fichier existant.
        $data['path'] = $request->file('image')->store('galerie', 'public');
        $data['created_by'] = $request->user()->id;
        unset($data['image']);

        Photo::create($data);

        return redirect()->route('admin.galerie.index')->with('status', 'Photo ajoutée.');
    }

    public function destroy(Photo $photo): RedirectResponse
    {
        Storage::disk('public')->delete($photo->path);
        $photo->delete();

        return redirect()->route('admin.galerie.index')->with('status', 'Photo supprimée.');
    }
}
