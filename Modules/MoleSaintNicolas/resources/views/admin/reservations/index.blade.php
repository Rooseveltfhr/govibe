@extends('layouts.admin')

@section('title', 'Réservations — Administration')

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-10">
        <h1 class="text-2xl font-bold text-msn-sea-900">Réservations</h1>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-msn-sand-200 bg-white">
            <table class="min-w-full divide-y divide-msn-sand-200 text-sm">
                <thead>
                    <tr class="text-left text-msn-sea-700">
                        <th class="px-4 py-3">Établissement</th>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Statut</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-msn-sand-200">
                    @forelse ($bookings as $booking)
                        <tr>
                            <td class="px-4 py-3 font-medium text-msn-sea-900">{{ $booking->establishment->name }}</td>
                            <td class="px-4 py-3 text-msn-sea-700">
                                {{ $booking->guest_name }}<br>
                                <span class="text-xs">{{ $booking->guest_phone }}</span>
                            </td>
                            <td class="px-4 py-3 text-msn-sea-700">
                                {{ $booking->starts_on->format('d/m/Y') }}
                                @if ($booking->ends_on) → {{ $booking->ends_on->format('d/m/Y') }} @endif
                                @if ($booking->reservation_time) à {{ $booking->reservation_time }} @endif
                                @if ($booking->party_size) · {{ $booking->party_size }} pers. @endif
                            </td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                    'bg-amber-100 text-amber-800' => $booking->status === 'pending',
                                    'bg-green-100 text-green-800' => $booking->status === 'confirmed',
                                    'bg-gray-100 text-gray-700' => $booking->status === 'cancelled',
                                ])>
                                    {{ ['pending' => 'En attente', 'confirmed' => 'Confirmée', 'cancelled' => 'Annulée'][$booking->status] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if ($booking->status !== 'confirmed')
                                    <form method="POST" action="{{ route('admin.reservations.update', $booking) }}" class="inline">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="status" value="confirmed">
                                        <button type="submit" class="text-green-700 hover:underline">Confirmer</button>
                                    </form>
                                @endif
                                @if ($booking->status !== 'cancelled')
                                    <form method="POST" action="{{ route('admin.reservations.update', $booking) }}" class="ml-3 inline">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="status" value="cancelled">
                                        <button type="submit" class="text-red-600 hover:underline">Annuler</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-msn-sea-700">Aucune réservation pour le moment.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
