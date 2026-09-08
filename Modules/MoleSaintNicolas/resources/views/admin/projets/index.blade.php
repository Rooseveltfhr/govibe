@extends('layouts.admin')

@section('title', 'Projets communautaires — Administration')

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-10">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-msn-sea-900">Projets communautaires</h1>
            <a href="{{ route('admin.projets.create') }}"
               class="rounded-lg bg-msn-terracotta-500 px-4 py-2 text-sm font-semibold text-white hover:bg-msn-terracotta-600">
                + Nouveau projet
            </a>
        </div>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-msn-sand-200 bg-white">
            <table class="min-w-full divide-y divide-msn-sand-200 text-sm">
                <thead>
                    <tr class="text-left text-msn-sea-700">
                        <th class="px-4 py-3">Titre</th>
                        <th class="px-4 py-3">Avancement</th>
                        <th class="px-4 py-3">Commentaires</th>
                        <th class="px-4 py-3">Statut</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-msn-sand-200">
                    @foreach ($projects as $project)
                        <tr>
                            <td class="px-4 py-3 font-medium text-msn-sea-900">{{ $project->title }}</td>
                            <td class="px-4 py-3 text-msn-sea-700">{{ ['planifie' => 'Planifié', 'en_cours' => 'En cours', 'termine' => 'Terminé'][$project->status] }}</td>
                            <td class="px-4 py-3 text-msn-sea-700">{{ $project->comments_count }}</td>
                            <td class="px-4 py-3"><x-content-status-badge :status="$project->content_status" /></td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.projets.edit', $project) }}" class="text-msn-sea-700 hover:underline">Modifier</a>
                                <form method="POST" action="{{ route('admin.projets.destroy', $project) }}" class="inline" onsubmit="return confirm('Supprimer ce projet ?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="ml-3 text-red-600 hover:underline">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <a href="{{ route('admin.projets.comments.index') }}" class="mt-6 inline-block text-sm font-semibold text-msn-sea-700 hover:underline">
            Modérer les commentaires &rarr;
        </a>
    </div>
@endsection
