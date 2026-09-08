@props(['status'])

@php
    $labels = [
        'verified' => ['Vérifié', 'bg-green-100 text-green-800'],
        'submitted' => ['Soumis', 'bg-amber-100 text-amber-800'],
        'needs_review' => ['À vérifier', 'bg-amber-100 text-amber-800'],
    ];
    [$label, $classes] = $labels[$status] ?? ['Inconnu', 'bg-gray-100 text-gray-700'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium $classes"]) }}>
    {{ $label }}
</span>
