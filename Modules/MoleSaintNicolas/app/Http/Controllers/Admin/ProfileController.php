<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('admin.profile.edit');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'min:10'],
        ]);

        // Le cast `password => hashed` du modèle User se charge du hachage.
        $request->user()->update(['password' => $data['password']]);

        return back()->with('status', 'Mot de passe mis à jour.');
    }
}
