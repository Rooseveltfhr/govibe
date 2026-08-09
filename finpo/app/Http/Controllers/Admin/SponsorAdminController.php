<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sponsor;
use Illuminate\Http\Request;

class SponsorAdminController extends Controller
{
    public function index()
    {
        return view('admin.sponsors.index', [
            'sponsors' => Sponsor::orderByRaw("case status when 'pending' then 0 else 1 end")->orderBy('sort')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.sponsors.form', ['sponsor' => new Sponsor(['status' => 'approved', 'level' => 'gold'])]);
    }

    public function store(Request $request)
    {
        Sponsor::create($this->validated($request));

        return redirect()->route('admin.sponsors.index')->with('ok', 'Sponsor ajouté.');
    }

    public function edit(Sponsor $sponsor)
    {
        return view('admin.sponsors.form', ['sponsor' => $sponsor]);
    }

    public function update(Request $request, Sponsor $sponsor)
    {
        $sponsor->update($this->validated($request));

        return redirect()->route('admin.sponsors.index')->with('ok', 'Sponsor mis à jour.');
    }

    public function destroy(Sponsor $sponsor)
    {
        $sponsor->delete();

        return back()->with('ok', 'Sponsor supprimé.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'          => 'required|string|max:190',
            'level'         => 'required|string|in:'.implode(',', array_keys(config('finpo.sponsor_levels'))),
            'logo_url'      => 'nullable|url|max:500',
            'website'       => 'nullable|url|max:190',
            'contact_name'  => 'nullable|string|max:190',
            'contact_email' => 'nullable|email|max:190',
            'contact_phone' => 'nullable|string|max:60',
            'message'       => 'nullable|string|max:3000',
            'status'        => 'required|string|in:pending,approved,rejected',
            'sort'          => 'nullable|integer|min:0',
        ]) + ['sort' => (int) $request->input('sort', 0)];
    }
}
