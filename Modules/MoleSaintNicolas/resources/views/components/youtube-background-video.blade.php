@props(['videoId', 'aspectRatio' => '16 / 9'])

{{-- Vidéo YouTube plein fond, en "cover" (recadrage assumé plutôt qu'évité —
     demande client). `aspectRatio` doit correspondre au format réel de la
     source ("16 / 9" pour une vidéo classique, "9 / 16" pour un Short
     vertical) : un mauvais ratio déformerait l'image. Pas de vidéo sur
     mobile : le "src" n'est posé par JS que si l'écran fait au moins 768px,
     donc aucune requête YouTube n'est même déclenchée sur téléphone (un
     simple hidden md:block en CSS n'aurait pas suffi — le navigateur charge
     un iframe même masqué). À placer dans un conteneur `position: relative`
     (ou `isolate`) avec une hauteur définie ; ce composant ne fournit que le
     calque vidéo + voile, pas le conteneur. --}}
@php
    // Survoler très largement en largeur (vidéo large) suffit à couvrir même
    // un conteneur inhabituellement haut ; l'inverse (survol en hauteur) sert
    // pour une source verticale sur un fond large. Le petit côté du ratio
    // décide quelle dimension pousser.
    [$rw, $rh] = array_map('trim', explode('/', $aspectRatio));
    $oversizeHeight = (float) $rw < (float) $rh;
@endphp
<div class="absolute inset-0 overflow-hidden" aria-hidden="true">
    <iframe
        class="pointer-events-none absolute top-1/2 left-1/2 hidden -translate-x-1/2 -translate-y-1/2 md:block {{ $oversizeHeight ? 'h-[500%] w-auto' : 'w-[300%] h-auto' }}"
        style="aspect-ratio: {{ $aspectRatio }}"
        data-video-src="https://www.youtube.com/embed/{{ $videoId }}?autoplay=1&mute=1&loop=1&playlist={{ $videoId }}&controls=0&rel=0&modestbranding=1&playsinline=1&disablekb=1&iv_load_policy=3"
        title="Vidéo de présentation — Môle-Saint-Nicolas"
        allow="autoplay; encrypted-media"
        x-data
        x-init="if (window.matchMedia('(min-width: 768px)').matches) { $el.src = $el.dataset.videoSrc }"
    ></iframe>
    <div class="absolute inset-0 bg-black/50"></div>
</div>
