@extends('layouts.admin', ['title' => 'Galerie'])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Galerie</h1>
    <a href="{{ route('admin.gallery.create') }}" class="btn btn-fp-primary btn-sm">+ Ajouter</a>
</div>
<div class="row g-3">
    @foreach ($items as $item)
        <div class="col-6 col-md-4 col-xl-3">
            <div class="fp-card overflow-hidden">
                <img src="{{ $item->thumb_url ?: $item->url }}" alt="" class="w-100" style="aspect-ratio: 4/3; object-fit: cover;">
                <div class="p-2 d-flex justify-content-between align-items-center">
                    <small class="fp-muted">{{ $item->type === 'video' ? '🎬' : '📷' }} {{ $item->edition }} · {{ \Illuminate\Support\Str::limit($item->caption, 22) }}</small>
                    <span class="text-nowrap">
                        <a href="{{ route('admin.gallery.edit', $item) }}" class="btn btn-sm btn-fp-outline py-0">✎</a>
                        <form method="post" action="{{ route('admin.gallery.destroy', $item) }}" class="d-inline" onsubmit="return confirm('Supprimer ?');">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger py-0">✕</button>
                        </form>
                    </span>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection
