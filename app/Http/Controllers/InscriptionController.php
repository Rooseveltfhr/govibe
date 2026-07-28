<?php

namespace App\Http\Controllers;

use App\Mail\AdminNotification;
use App\Mail\InscriptionConfirmation;
use App\Models\Formation;
use App\Models\Inscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class InscriptionController extends Controller
{
    public function create()
    {
        $formations = Formation::where('active', true)->get();
        return view('inscription.create', compact('formations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom_complet'    => 'required|string|max:255',
            'sexe'           => 'required|in:Masculin,Féminin',
            'date_naissance' => 'required|date|before:today',
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
        ], [
            'nom_complet.required'    => 'Le nom complet est obligatoire.',
            'sexe.required'           => 'Le sexe est obligatoire.',
            'date_naissance.required' => 'La date de naissance est obligatoire.',
            'date_naissance.before'   => 'La date de naissance doit être dans le passé.',
            'telephone.required'      => 'Le téléphone est obligatoire.',
            'email.required'          => 'L\'adresse email est obligatoire.',
            'email.email'             => 'L\'adresse email n\'est pas valide.',
            'departement.required'    => 'Le département est obligatoire.',
            'ville.required'          => 'La ville est obligatoire.',
            'niveau_etude.required'   => 'Le niveau d\'étude est obligatoire.',
            'formation_id.required'   => 'La formation est obligatoire.',
            'formation_id.exists'     => 'La formation sélectionnée n\'existe pas.',
            'source_info.required'    => 'Ce champ est obligatoire.',
        ]);

        $numero = Inscription::generateNumero();

        $inscription = Inscription::create(array_merge($validated, [
            'numero_inscription' => $numero,
        ]));

        $qrData = url('/inscription/qr/' . $inscription->id);
        $qrCode = base64_encode(QrCode::format('png')->size(200)->generate($qrData));
        $inscription->update(['qr_code' => $qrCode]);

        try {
            Mail::to($inscription->email)->send(new InscriptionConfirmation($inscription));
            Mail::to(env('ADMIN_EMAIL', 'govibeht@gmail.com'))->send(new AdminNotification($inscription));
        } catch (\Exception $e) {
            // Email failure should not block registration
        }

        $formation = Formation::find($validated['formation_id']);

        return view('inscription.success', compact('inscription', 'formation'));
    }

    public function qr(Inscription $inscription)
    {
        return view('inscription.qr', compact('inscription'));
    }

    public function scan(Request $request)
    {
        $inscription = Inscription::where('numero_inscription', $request->numero)->first();

        if (!$inscription) {
            return response()->json(['success' => false, 'message' => 'Participant non trouvé.']);
        }

        $inscription->update(['present' => true, 'scanned_at' => now()]);

        return response()->json([
            'success'  => true,
            'message'  => 'Présence enregistrée pour ' . $inscription->nom_complet,
            'data'     => $inscription->load('formation'),
        ]);
    }
}
