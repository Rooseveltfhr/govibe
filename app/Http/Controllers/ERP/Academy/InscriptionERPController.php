<?php

namespace App\Http\Controllers\ERP\Academy;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\Inscription;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class InscriptionERPController extends Controller
{
    public function index(Request $request)
    {
        $query = Inscription::with('formation');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('nom_complet', 'like', "%$s%")
                  ->orWhere('email', 'like', "%$s%")
                  ->orWhere('telephone', 'like', "%$s%")
                  ->orWhere('numero_inscription', 'like', "%$s%");
            });
        }

        if ($request->filled('formation_id')) {
            $query->where('formation_id', $request->formation_id);
        }

        if ($request->filled('departement')) {
            $query->where('departement', $request->departement);
        }

        if ($request->filled('presence') && in_array($request->presence, ['present', 'absent'])) {
            $query->where('present', $request->presence === 'present');
        }

        $inscriptions = $query->latest()->paginate(25)->withQueryString();
        $formations   = $this->safeQuery(fn() => Formation::orderBy('nom')->get(), collect());
        $departements = $this->safeQuery(
            fn() => Inscription::distinct()->pluck('departement')->filter()->sort()->values(),
            collect()
        );

        $stats = [
            'total'    => $this->safeCount(fn() => Inscription::count()),
            'presents' => $this->safeCount(fn() => Inscription::where('present', true)->count()),
            'absents'  => $this->safeCount(fn() => Inscription::where('present', false)->count()),
            'formations' => $this->safeCount(fn() => Formation::count()),
        ];

        return view('erp.academy.inscriptions.index', compact(
            'inscriptions', 'formations', 'departements', 'stats'
        ));
    }

    public function show(Inscription $inscription)
    {
        $inscription->load('formation');
        return view('erp.academy.inscriptions.show', compact('inscription'));
    }

    public function togglePresence(Inscription $inscription)
    {
        $nowPresent = !$inscription->present;
        $inscription->update([
            'present'    => $nowPresent,
            'scanned_at' => $nowPresent ? now() : null,
        ]);
        $label = $nowPresent ? 'marqué(e) présent(e)' : 'marqué(e) absent(e)';
        return back()->with('success', "{$inscription->nom_complet} — {$label}.");
    }

    public function destroy(Inscription $inscription)
    {
        $nom = $inscription->nom_complet;
        $inscription->delete();
        return redirect()->route('erp.academy.inscriptions.index')
            ->with('success', "Inscription de {$nom} supprimée.");
    }

    public function attestation(Inscription $inscription)
    {
        $inscription->load('formation');
        $pdf = Pdf::loadView('admin.inscriptions.attestation', compact('inscription'));
        return $pdf->download('attestation-' . $inscription->numero_inscription . '.pdf');
    }

    private function safeCount(callable $fn, int $default = 0): int
    {
        try { return $fn(); } catch (\Exception $e) { return $default; }
    }

    private function safeQuery(callable $fn, $default)
    {
        try { return $fn(); } catch (\Exception $e) { return $default; }
    }
}
