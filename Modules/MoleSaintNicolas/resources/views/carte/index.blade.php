@extends('layouts.public')

@section('title', 'Carte interactive — Môle-Saint-Nicolas')
@section('meta_description', "Carte interactive de Môle-Saint-Nicolas : communes, lieux historiques et établissements.")

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-16 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-msn-sand-100 sm:text-4xl">Carte interactive</h1>
        <p class="mt-3 text-msn-sand-200">
            Communes, lieux historiques et établissements géolocalisés — la carte se remplit au
            fur et à mesure que les coordonnées sont confirmées par l'équipe éditoriale.
        </p>

        <div class="mt-8">
            <x-leaflet-map :markers="$markers" :zoom="11" class="h-[32rem] w-full" />
        </div>

        @if ($markers->isEmpty())
            <p class="mt-4 text-sm text-msn-sand-200">
                [Information à compléter — aucune coordonnée confirmée pour l'instant. La carte est
                centrée sur Môle-Saint-Nicolas.]
            </p>
        @endif
    </div>
@endsection
