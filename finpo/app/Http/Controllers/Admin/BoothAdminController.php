<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booth;
use Illuminate\Http\Request;

class BoothAdminController extends Controller
{
    public function index()
    {
        return view('admin.booths.index', [
            'booths' => Booth::with('exhibitor')->orderBy('zone')->orderBy('code')->get()->groupBy('zone'),
        ]);
    }

    public function create()
    {
        return view('admin.booths.form', ['booth' => new Booth(['status' => 'available', 'zone' => 'A', 'size' => '3x3'])]);
    }

    public function store(Request $request)
    {
        Booth::create($this->validated($request));

        return redirect()->route('admin.booths.index')->with('ok', 'Stand créé.');
    }

    public function edit(Booth $booth)
    {
        return view('admin.booths.form', ['booth' => $booth]);
    }

    public function update(Request $request, Booth $booth)
    {
        $booth->update($this->validated($request, $booth));

        return redirect()->route('admin.booths.index')->with('ok', 'Stand mis à jour.');
    }

    public function destroy(Booth $booth)
    {
        if ($booth->exhibitor()->exists()) {
            return back()->withErrors(['delete' => 'Un exposant occupe ce stand.']);
        }
        $booth->delete();

        return back()->with('ok', 'Stand supprimé.');
    }

    private function validated(Request $request, ?Booth $current = null): array
    {
        return $request->validate([
            'code'   => 'required|string|max:20|unique:booths,code'.($current ? ','.$current->id : ''),
            'zone'   => 'required|string|max:10',
            'size'   => 'required|string|max:20',
            'price'  => 'required|integer|min:0',
            'status' => 'required|string|in:available,reserved,sold',
        ]);
    }
}
