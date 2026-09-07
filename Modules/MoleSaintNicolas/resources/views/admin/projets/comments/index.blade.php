@extends('layouts.admin')

@section('title', 'Modération des commentaires — Administration')

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-10">
        <h1 class="text-2xl font-bold text-msn-sea-900">Modération des commentaires</h1>
        <p class="mt-1 text-sm text-msn-sea-700">
            Un commentaire n'apparaît sur le site public qu'une fois approuvé.
        </p>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-msn-sand-200 bg-white">
            <table class="min-w-full divide-y divide-msn-sand-200 text-sm">
                <thead>
                    <tr class="text-left text-msn-sea-700">
                        <th class="px-4 py-3">Projet</th>
                        <th class="px-4 py-3">Auteur</th>
                        <th class="px-4 py-3">Commentaire</th>
                        <th class="px-4 py-3">Statut</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-msn-sand-200">
                    @foreach ($comments as $comment)
                        <tr>
                            <td class="px-4 py-3 font-medium text-msn-sea-900">{{ $comment->project->title }}</td>
                            <td class="px-4 py-3 text-msn-sea-700">{{ $comment->author_name }}</td>
                            <td class="px-4 py-3 text-msn-sea-700">{{ \Illuminate\Support\Str::limit($comment->body, 80) }}</td>
                            <td class="px-4 py-3">
                                @if ($comment->is_approved)
                                    <span class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">Approuvé</span>
                                @else
                                    <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">En attente</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @unless ($comment->is_approved)
                                    <form method="POST" action="{{ route('admin.projets.comments.approve', $comment) }}" class="inline">
                                        @csrf @method('PUT')
                                        <button type="submit" class="text-green-700 hover:underline">Approuver</button>
                                    </form>
                                @endunless
                                <form method="POST" action="{{ route('admin.projets.comments.destroy', $comment) }}" class="inline" onsubmit="return confirm('Supprimer ce commentaire ?')">
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
