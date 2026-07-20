<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsPost;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsAdminController extends Controller
{
    public function index()
    {
        return view('admin.news.index', ['posts' => NewsPost::latest('published_at')->latest()->get()]);
    }

    public function create()
    {
        return view('admin.news.form', ['post' => new NewsPost(['tag' => 'Actualité'])]);
    }

    public function store(Request $request)
    {
        NewsPost::create($this->validated($request));

        return redirect()->route('admin.news.index')->with('ok', 'Article créé.');
    }

    public function edit(NewsPost $news)
    {
        return view('admin.news.form', ['post' => $news]);
    }

    public function update(Request $request, NewsPost $news)
    {
        $news->update($this->validated($request, $news));

        return redirect()->route('admin.news.index')->with('ok', 'Article mis à jour.');
    }

    public function destroy(NewsPost $news)
    {
        $news->delete();

        return back()->with('ok', 'Article supprimé.');
    }

    private function validated(Request $request, ?NewsPost $current = null): array
    {
        $data = $request->validate([
            'title'        => 'required|string|max:190',
            'tag'          => 'required|string|max:60',
            'cover_url'    => 'nullable|url|max:500',
            'excerpt'      => 'nullable|string|max:500',
            'body'         => 'nullable|string|max:50000',
            'published_at' => 'nullable|date',
        ]);

        if (! $current || $current->title !== $data['title']) {
            $slug = Str::slug($data['title']);
            $base = $slug;
            $i = 1;
            while (NewsPost::where('slug', $slug)->when($current, fn ($q) => $q->where('id', '!=', $current->id))->exists()) {
                $slug = $base.'-'.(++$i);
            }
            $data['slug'] = $slug;
        }

        return $data;
    }
}
