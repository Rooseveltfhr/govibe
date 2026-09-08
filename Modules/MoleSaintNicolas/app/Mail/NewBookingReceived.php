<?php

namespace App\Mail;

use App\Models\Etablissements\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewBookingReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function build(): self
    {
        return $this
            ->subject("Nouvelle demande de réservation — {$this->booking->establishment->name}")
            ->markdown('emails.booking-received');
    }
}
