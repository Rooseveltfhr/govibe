<?php

namespace App\Http\Controllers;

use App\Models\Page;

class PageController extends Controller
{
    public function about()
    {
        return $this->show('a-propos');
    }

    public function legal()
    {
        return $this->show('mentions-legales');
    }

    private function show(string $slug)
    {
        $page = Page::where('slug', $slug)->firstOrFail();

        return view('pages.show', compact('page'));
    }
}
