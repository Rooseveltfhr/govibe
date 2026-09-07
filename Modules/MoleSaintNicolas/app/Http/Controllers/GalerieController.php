<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use Illuminate\Http\Request;

class GalerieController extends Controller
{
    public function index(Request $request)
    {
        $category = $request->query('categorie');

        $photos = Photo::when($category, fn ($query) => $query->where('category', $category))
            ->orderByDesc('created_at')
            ->get();

        $categories = Photo::whereNotNull('category')->distinct()->pluck('category');

        return view('galerie.index', compact('photos', 'categories', 'category'));
    }
}
