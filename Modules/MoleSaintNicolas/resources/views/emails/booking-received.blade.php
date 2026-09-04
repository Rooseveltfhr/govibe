<x-mail::message>
# Nouvelle demande de réservation

**Établissement :** {{ $booking->establishment->name }} ({{ $booking->establishment->type }})

**Client :** {{ $booking->guest_name }} — {{ $booking->guest_phone }}
@if ($booking->guest_email)
({{ $booking->guest_email }})
@endif

**Date :** {{ $booking->starts_on->translatedFormat('d F Y') }}
@if ($booking->ends_on)
→ {{ $booking->ends_on->translatedFormat('d F Y') }}
@endif
@if ($booking->reservation_time)
à {{ $booking->reservation_time }}
@endif
@if ($booking->party_size)
— {{ $booking->party_size }} personne(s)
@endif

@if ($booking->notes)
**Note du client :** {{ $booking->notes }}
@endif

Contactez le client pour confirmer manuellement — aucun paiement n'est pris en ligne.

<x-mail::button :url="route('admin.reservations.index')">
Voir les réservations
</x-mail::button>
</x-mail::message>
