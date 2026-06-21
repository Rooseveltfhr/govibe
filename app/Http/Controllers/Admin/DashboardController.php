<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\Inscription;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $totalInscriptions = Inscription::count();
        $totalHommes       = Inscription::where('sexe', 'Masculin')->count();
        $totalFemmes       = Inscription::where('sexe', 'Féminin')->count();
        $totalFormations   = Formation::count();

        $parFormation = Formation::withCount('inscriptions')->get();

        $parDepartement = Inscription::select('departement', DB::raw('count(*) as total'))
            ->groupBy('departement')
            ->orderByDesc('total')
            ->get();

        $recentes = Inscription::with('formation')->latest()->take(5)->get();

        return view('admin.dashboard', compact(
            'totalInscriptions',
            'totalHommes',
            'totalFemmes',
            'totalFormations',
            'parFormation',
            'parDepartement',
            'recentes'
        ));
    }
}
