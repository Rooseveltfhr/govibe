<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GalleryItem;
use Illuminate\Http\Request;

class GalleryAdminController extends Controller
{
    public function index()
    {
        return view('admin.gallery.index', ['items' => GalleryItem::orderByDesc('edition')->orderBy('sort')->get()]);
    }

    public function create()
    {
        return view('admin.gallery.form', ['item' => new GalleryItem(['type' => 'photo', 'edition' => now()->year])]);
    }

    public function store(Request $request)
    {
        GalleryItem::create($this->validated($request));

        return redirect()->route('admin.gallery.index')->with('ok', 'Élément ajouté à la galerie.');
    }

    public function edit(GalleryItem $gallery)
    {
        return view('admin.gallery.form', ['item' => $gallery]);
    }

    public function update(Request $request, GalleryItem $gallery)
    {
        $gallery->update($this->validated($request));

        return redirect()->route('admin.gallery.index')->with('ok', 'Élément mis à jour.');
    }

    public function destroy(GalleryItem $gallery)
    {
        $gallery->delete();

        return back()->with('ok', 'Élément supprimé.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'type'      => 'required|string|in:photo,video',
            'url'       => 'required|url|max:500',
            'thumb_url' => 'nullable|url|max:500',
            'caption'   => 'nullable|string|max:190',
            'edition'   => 'required|integer|min:2020|max:2100',
            'sort'      => 'nullable|integer|min:0',
        ]) + ['sort' => (int) $request->input('sort', 0)];
    }
}
