@extends('layouts.admin')

@section('title', 'Périodes historiques — Administration')

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-10">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-msn-sea-900">Périodes historiques</h1>
            <a href="{{ route('admin.histoire.periods.create') }}"
               class="rounded-lg bg-msn-terracotta-500 px-4 py-2 text-sm font-semibold text-white hover:bg-msn-terracotta-600">
                + Nouvelle période
            </a>
        </div>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-msn-sand-200 bg-white">
            <table class="min-w-full divide-y divide-msn-sand-200 text-sm">
                <thead>
                    <tr class="text-left text-msn-sea-700">
                        <th class="px-4 py-3">Nom</th>
                        <th class="px-4 py-3">Années</th>
                        <th class="px-4 py-3">Ordre</th>
                        <th class="px-4 py-3">Statut</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-msn-sand-200">
                    @foreach ($periods as $period)
                        <tr>
                            <td class="px-4 py-3 font-medium text-msn-sea-900">{{ $period->name }}</td>
                            <td class="px-4 py-3 text-msn-sea-700">{{ $period->start_year }}@if($period->end_year) – {{ $period->end_year }}@endif</td>
                            <td class="px-4 py-3 text-msn-sea-700">{{ $period->display_order }}</td>
                            <td class="px-4 py-3"><x-content-status-badge :status="$period->content_status" /></td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.histoire.periods.edit', $period) }}" class="text-msn-sea-700 hover:underline">Modifier</a>
                                <form method="POST" action="{{ route('admin.histoire.periods.destroy', $period) }}" class="inline" onsubmit="return confirm('Supprimer cette période ?')">
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
