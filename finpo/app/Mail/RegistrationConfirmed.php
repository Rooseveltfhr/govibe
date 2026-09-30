<?php

namespace App\Mail;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Registration $registration)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: config('finpo.name').' — Confirmation d\'inscription '.$this->registration->number,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.registration');
    }
}
