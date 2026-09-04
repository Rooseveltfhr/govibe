@extends('layouts.admin')

@section('title', 'Événements historiques — Administration')

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-10">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-msn-sea-900">Événements historiques</h1>
            <a href="{{ route('admin.histoire.events.create') }}"
               class="rounded-lg bg-msn-terracotta-500 px-4 py-2 text-sm font-semibold text-white hover:bg-msn-terracotta-600">
                + Nouvel événement
            </a>
        </div>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-msn-sand-200 bg-white">
            <table class="min-w-full divide-y divide-msn-sand-200 text-sm">
                <thead>
                    <tr class="text-left text-msn-sea-700">
                        <th class="px-4 py-3">Titre</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Période</th>
                        <th class="px-4 py-3">Statut</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-msn-sand-200">
                    @foreach ($events as $event)
                        <tr>
                            <td class="px-4 py-3 font-medium text-msn-sea-900">{{ $event->title }}</td>
                            <td class="px-4 py-3 text-msn-sea-700">{{ $event->happened_on?->format('d/m/Y') ?? $event->circa_year }}</td>
                            <td class="px-4 py-3 text-msn-sea-700">{{ $event->period?->name ?? '—' }}</td>
                            <td class="px-4 py-3"><x-content-status-badge :status="$event->content_status" /></td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.histoire.events.edit', $event) }}" class="text-msn-sea-700 hover:underline">Modifier</a>
                                <form method="POST" action="{{ route('admin.histoire.events.destroy', $event) }}" class="inline" onsubmit="return confirm('Supprimer cet événement ?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="ml-3 text-red-600 hover:underline">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
