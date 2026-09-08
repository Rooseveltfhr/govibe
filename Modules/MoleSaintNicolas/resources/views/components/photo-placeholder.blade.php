@props(['label' => 'Photo à venir', 'icon' => 'image'])

@php
    $icons = [
        'image' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M14 8h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
        'landmark' => 'M3 21h18M5 21V9l7-5 7 5v12M9 21v-6h6v6',
        'map' => 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center gap-2 border-2 border-dashed border-msn-sand-200 bg-msn-sand-100 text-msn-ink-700']) }}>
    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$icon] ?? $icons['image'] }}" />
    </svg>
    <span class="text-xs font-medium uppercase tracking-wide opacity-80">{{ $label }}</span>
</div>
