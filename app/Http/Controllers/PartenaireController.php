<?php

namespace App\Http\Controllers;

use App\Mail\PartenaireNotification;
use App\Models\Partenaire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class PartenaireController extends Controller
{
    public function index()
    {
        // Seuls les partenaires explicitement publiés depuis l'ERP.
        $partenaires = Partenaire::vitrine()->get();

        return view('partenaires', compact('partenaires'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'prenom'           => 'required|string|max:100',
            'nom'              => 'required|string|max:100',
            'organisation'     => 'nullable|string|max:255',
            'pays'             => 'required|string|max:100',
            'ville'            => 'required|string|max:100',
            'telephone'        => 'required|string|max:30',
            'email'            => 'required|email|max:255',
            'type_partenariat' => 'required|string|max:100',
            'description'      => 'nullable|string|max:2000',
        ], [
            'prenom.required'           => 'Le prénom est obligatoire.',
            'nom.required'              => 'Le nom est obligatoire.',
            'pays.required'             => 'Le pays est obligatoire.',
            'ville.required'            => 'La ville est obligatoire.',
            'telephone.required'        => 'Le téléphone est obligatoire.',
            'email.required'            => 'L\'email est obligatoire.',
            'email.email'               => 'L\'email n\'est pas valide.',
            'type_partenariat.required' => 'Veuillez sélectionner un type de partenariat.',
        ]);

        $partenaire = Partenaire::create($validated);

        try {
            Mail::to(env('ADMIN_EMAIL', 'govibeht@gmail.com'))
                ->send(new PartenaireNotification($partenaire));
        } catch (\Exception) {
            // Mail failure does not block submission
        }

        return back()->with('success', 'Votre demande de partenariat a été soumise avec succès ! Nous vous contacterons sous 48h.');
    }
}
