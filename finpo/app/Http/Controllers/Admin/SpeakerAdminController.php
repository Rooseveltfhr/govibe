<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Speaker;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SpeakerAdminController extends Controller
{
    public function index()
    {
        return view('admin.speakers.index', ['speakers' => Speaker::orderBy('sort')->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('admin.speakers.form', ['speaker' => new Speaker(['active' => true, 'country' => 'Haïti'])]);
    }

    public function store(Request $request)
    {
        Speaker::create($this->validated($request));

        return redirect()->route('admin.speakers.index')->with('ok', 'Intervenant ajouté.');
    }

    public function edit(Speaker $speaker)
    {
        return view('admin.speakers.form', ['speaker' => $speaker]);
    }

    public function update(Request $request, Speaker $speaker)
    {
        $speaker->update($this->validated($request, $speaker));

        return redirect()->route('admin.speakers.index')->with('ok', 'Intervenant mis à jour.');
    }

    public function destroy(Speaker $speaker)
    {
        $speaker->delete();

        return back()->with('ok', 'Intervenant supprimé.');
    }

    private function validated(Request $request, ?Speaker $current = null): array
    {
        $data = $request->validate([
            'name'        => 'required|string|max:190',
            'position'    => 'nullable|string|max:190',
            'institution' => 'nullable|string|max:190',
            'country'     => 'required|string|max:120',
            'category'    => 'required|string|in:'.implode(',', array_keys(config('finpo.speaker_categories'))),
            'photo_url'   => 'nullable|url|max:500',
            'bio'         => 'nullable|string|max:5000',
            'topic'       => 'nullable|string|max:190',
            'linkedin'    => 'nullable|url|max:190',
            'facebook'    => 'nullable|url|max:190',
            'website'     => 'nullable|url|max:190',
            'featured'    => 'nullable|boolean',
            'active'      => 'nullable|boolean',
            'sort'        => 'nullable|integer|min:0',
        ]);

        $data['featured'] = $request->boolean('featured');
        $data['active'] = $request->boolean('active');
        $data['sort'] = $data['sort'] ?? 0;

        if (! $current || $current->name !== $data['name']) {
            $slug = Str::slug($data['name']);
            $base = $slug;
            $i = 1;
            while (Speaker::where('slug', $slug)->when($current, fn ($q) => $q->where('id', '!=', $current->id))->exists()) {
                $slug = $base.'-'.(++$i);
            }
            $data['slug'] = $slug;
        }

        return $data;
    }
}
