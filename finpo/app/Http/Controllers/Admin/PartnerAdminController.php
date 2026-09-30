<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;

class PartnerAdminController extends Controller
{
    public function index()
    {
        return view('admin.partners.index', [
            'partners' => Partner::orderByRaw("case status when 'pending' then 0 else 1 end")->orderBy('sort')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.partners.form', ['partner' => new Partner(['status' => 'approved'])]);
    }

    public function store(Request $request)
    {
        Partner::create($this->validated($request));

        return redirect()->route('admin.partners.index')->with('ok', 'Partenaire ajouté.');
    }

    public function edit(Partner $partner)
    {
        return view('admin.partners.form', ['partner' => $partner]);
    }

    public function update(Request $request, Partner $partner)
    {
        $partner->update($this->validated($request));

        return redirect()->route('admin.partners.index')->with('ok', 'Partenaire mis à jour.');
    }

    public function destroy(Partner $partner)
    {
        $partner->delete();

        return back()->with('ok', 'Partenaire supprimé.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'          => 'required|string|max:190',
            'category'      => 'required|string|in:'.implode(',', array_keys(config('finpo.partner_categories'))),
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
