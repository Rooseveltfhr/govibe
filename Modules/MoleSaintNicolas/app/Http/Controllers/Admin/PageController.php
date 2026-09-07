<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function index()
    {
        $pages = Page::orderBy('title')->get();

        return view('admin.pages.index', compact('pages'));
    }

    public function create()
    {
        $page = new Page;

        return view('admin.pages.form', compact('page'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        Page::create($data);

        return redirect()->route('admin.pages.index')->with('status', 'Page créée.');
    }

    public function edit(Page $page)
    {
        return view('admin.pages.form', compact('page'));
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $data = $page->applyVerificationStamp($this->validated($request), $request->user());

        $page->update($data);

        return redirect()->route('admin.pages.index')->with('status', 'Page mise à jour.');
    }

    public function destroy(Page $page): RedirectResponse
    {
        $page->delete();

        return redirect()->route('admin.pages.index')->with('status', 'Page supprimée.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'alpha_dash', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'content_status' => ['required', 'in:verified,submitted,needs_review'],
            'source_note' => ['nullable', 'string'],
        ]);
    }
}
