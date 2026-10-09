@extends('layouts.admin', ['title' => 'Messages'])

@section('content')
<h1 class="h3 mb-4">Messages de contact</h1>
<div class="d-grid gap-3">
    @forelse ($messages as $message)
        <div class="fp-card p-3">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                <div>
                    <strong>{{ $message->name }}</strong>
                    <span class="fp-muted small">· {{ $message->email }} {{ $message->phone ? '· '.$message->phone : '' }}</span>
                    @if ($message->subject)<span class="fp-chip ms-1">{{ $message->subject }}</span>@endif
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <span class="fp-muted small">{{ $message->created_at->format('d/m/Y H:i') }}</span>
                    <a class="btn btn-sm btn-fp-outline py-0" href="mailto:{{ $message->email }}">Répondre</a>
                    <form method="post" action="{{ route('admin.messages.destroy', $message) }}" onsubmit="return confirm('Supprimer ?');">
                        @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger py-0">✕</button>
                    </form>
                </div>
            </div>
            <p class="mb-0 small" style="white-space: pre-line;">{{ $message->message }}</p>
        </div>
    @empty
        <p class="fp-muted">Aucun message.</p>
    @endforelse
    {{ $messages->links() }}
</div>
@endsection
