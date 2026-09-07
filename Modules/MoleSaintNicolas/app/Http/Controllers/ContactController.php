<?php

namespace App\Http\Controllers;

use App\Mail\NewContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function show()
    {
        return view('contact.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $contactMessage = ContactMessage::create($data);

        $adminEmails = User::role(['super_admin', 'admin'])->pluck('email');
        if ($adminEmails->isNotEmpty()) {
            Mail::to($adminEmails->all())->send(new NewContactMessageReceived($contactMessage));
        }

        return redirect()
            ->route('contact.show')
            ->with('status', 'Votre message a bien été envoyé. Nous vous répondrons dès que possible.');
    }
}
