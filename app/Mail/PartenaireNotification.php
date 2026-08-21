<?php

namespace App\Mail;

use App\Models\Partenaire;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartenaireNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Partenaire $partenaire) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🤝 Nouvelle demande de partenariat — ' . $this->partenaire->nom_complet,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.partenaire-notification',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
