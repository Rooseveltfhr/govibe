<?php

namespace App\Http\Controllers\Admin;

use App\Exports\InscriptionsExport;
use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\Inscription;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class InscriptionController extends Controller
{
    public function index(Request $request)
    {
        $query = Inscription::with('formation');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nom_complet', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%")
                  ->orWhere('telephone', 'like', "%$search%")
                  ->orWhere('numero_inscription', 'like', "%$search%");
            });
        }

        if ($request->filled('formation_id')) {
            $query->where('formation_id', $request->formation_id);
        }

        if ($request->filled('departement')) {
            $query->where('departement', $request->departement);
        }

        $inscriptions = $query->latest()->paginate(20)->withQueryString();
        $formations   = Formation::all();
        $departements = Inscription::distinct()->pluck('departement')->sort()->values();

        return view('admin.inscriptions.index', compact('inscriptions', 'formations', 'departements'));
    }

    public function show(Inscription $inscription)
    {
        return view('admin.inscriptions.show', compact('inscription'));
    }

    public function edit(Inscription $inscription)
    {
        $formations = Formation::all();
        return view('admin.inscriptions.edit', compact('inscription', 'formations'));
    }

    public function update(Request $request, Inscription $inscription)
    {
        $validated = $request->validate([
            'nom_complet'    => 'required|string|max:255',
            'sexe'           => 'required|in:Masculin,Féminin',
            'date_naissance' => 'required|date',
            'telephone'      => 'required|string|max:20',
            'email'          => 'required|email|max:255',
            'departement'    => 'required|string|max:100',
            'ville'          => 'required|string|max:100',
            'profession'     => 'nullable|string|max:255',
            'niveau_etude'   => 'required|string|max:100',
            'formation_id'   => 'required|exists:formations,id',
            'source_info'    => 'required|string|max:255',
            'objectif'       => 'nullable|string|max:1000',
            'attentes'       => 'nullable|string|max:1000',
        ]);

        $inscription->update($validated);

        return redirect()->route('admin.inscriptions.index')
            ->with('success', 'Participant mis à jour avec succès.');
    }

    public function destroy(Inscription $inscription)
    {
        $inscription->delete();
        return redirect()->route('admin.inscriptions.index')
            ->with('success', 'Participant supprimé avec succès.');
    }

    public function exportExcel(Request $request)
    {
        $formationId = $request->formation_id;
        $filename    = 'inscriptions-govibe-' . date('Y-m-d') . '.xlsx';
        return Excel::download(new InscriptionsExport($formationId), $filename);
    }

    public function exportCsv(Request $request)
    {
        $formationId = $request->formation_id;
        $filename    = 'inscriptions-govibe-' . date('Y-m-d') . '.csv';
        return Excel::download(new InscriptionsExport($formationId), $filename, \Maatwebsite\Excel\Excel::CSV);
    }

    public function print(Request $request)
    {
        $query = Inscription::with('formation');

        if ($request->filled('formation_id')) {
            $query->where('formation_id', $request->formation_id);
        }

        $inscriptions = $query->latest()->get();
        $formation    = $request->filled('formation_id') ? Formation::find($request->formation_id) : null;

        return view('admin.inscriptions.print', compact('inscriptions', 'formation'));
    }

    public function attestation(Inscription $inscription)
    {
        $pdf = Pdf::loadView('admin.inscriptions.attestation', compact('inscription'));
        return $pdf->download('attestation-' . $inscription->numero_inscription . '.pdf');
    }
}
