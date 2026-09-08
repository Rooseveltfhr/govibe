@extends('layouts.admin')

@section('title', 'Sections communales — Administration')

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-10">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-msn-sea-900">Sections communales</h1>
            <a href="{{ route('admin.territoire.sections.create') }}"
               class="rounded-lg bg-msn-terracotta-500 px-4 py-2 text-sm font-semibold text-white hover:bg-msn-terracotta-600">
                + Nouvelle section
            </a>
        </div>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-msn-sand-200 bg-white">
            <table class="min-w-full divide-y divide-msn-sand-200 text-sm">
                <thead>
                    <tr class="text-left text-msn-sea-700">
                        <th class="px-4 py-3">Nom</th>
                        <th class="px-4 py-3">Commune</th>
                        <th class="px-4 py-3">Statut</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-msn-sand-200">
                    @foreach ($sections as $section)
                        <tr>
                            <td class="px-4 py-3 font-medium text-msn-sea-900">{{ $section->name }}</td>
                            <td class="px-4 py-3 text-msn-sea-700">{{ $section->commune->name }}</td>
                            <td class="px-4 py-3"><x-content-status-badge :status="$section->content_status" /></td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.territoire.sections.edit', $section) }}" class="text-msn-sea-700 hover:underline">Modifier</a>
                                <form method="POST" action="{{ route('admin.territoire.sections.destroy', $section) }}" class="inline" onsubmit="return confirm('Supprimer cette section ?')">
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
