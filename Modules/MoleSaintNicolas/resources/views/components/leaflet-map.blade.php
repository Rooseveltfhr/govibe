@props(['markers' => [], 'centerLat' => 19.8047, 'centerLng' => -73.3778, 'zoom' => 13])

@php
    $mapId = 'map-'.\Illuminate\Support\Str::random(8);
@endphp

<div id="{{ $mapId }}" {{ $attributes->merge(['class' => 'h-96 w-full rounded-2xl border border-msn-sea-700']) }}></div>

{{-- Pas de hash d'intégrité (SRI) ici : un hash figé dans le code et non
     vérifiable dans cet environnement, s'il devient incorrect (changement
     de version du CDN), bloquerait silencieusement le chargement de la
     carte plutôt que d'améliorer la sécurité. --}}
@once
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endonce

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        (function () {
            var map = L.map('{{ $mapId }}').setView([{{ $centerLat }}, {{ $centerLng }}], {{ $zoom }});

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 18,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(map);

            var markers = @json($markers);
            markers.forEach(function (m) {
                var marker = L.marker([m.lat, m.lng]).addTo(map);
                if (m.label) {
                    marker.bindPopup(m.href ? '<a href="' + m.href + '">' + m.label + '</a>' : m.label);
                }
            });
        })();
    </script>
@endpush
