@extends('layouts.admin')

@section('title', 'Messages — Administration')

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-10">
        <h1 class="text-2xl font-bold text-msn-sea-900">Messages de contact</h1>

        <div class="mt-6 space-y-4">
            @forelse ($messages as $message)
                <div @class([
                    'rounded-2xl border bg-white p-4',
                    'border-msn-sand-200' => $message->isRead(),
                    'border-msn-terracotta-500' => ! $message->isRead(),
                ])>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="font-semibold text-msn-sea-900">
                                {{ $message->name }}
                                @unless ($message->isRead())
                                    <span class="ml-1 rounded-full bg-msn-terracotta-500/10 px-2 py-0.5 text-xs font-medium text-msn-terracotta-500">Nouveau</span>
                                @endunless
                            </p>
                            <p class="text-sm text-msn-sea-700">
                                {{ $message->email }}
                                @if ($message->phone) · {{ $message->phone }} @endif
                                · {{ $message->created_at->format('d/m/Y H:i') }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3 text-sm">
                            @unless ($message->isRead())
                                <form method="POST" action="{{ route('admin.messages.markRead', $message) }}">
                                    @csrf @method('PUT')
                                    <button type="submit" class="text-green-700 hover:underline">Marquer comme lu</button>
                                </form>
                            @endunless
                            <form method="POST" action="{{ route('admin.messages.destroy', $message) }}"
                                  onsubmit="return confirm('Supprimer ce message ?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Supprimer</button>
                            </form>
                        </div>
                    </div>
                    <p class="mt-3 whitespace-pre-line text-sm text-msn-sea-700">{{ $message->message }}</p>
                </div>
            @empty
                <p class="text-msn-sea-700">Aucun message pour le moment.</p>
            @endforelse
        </div>
    </div>
@endsection
