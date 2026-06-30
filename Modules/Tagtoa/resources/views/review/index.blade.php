@extends('tagtoa::layouts.dashboard')
@section('title', __('Avis clients'))
@section('page', __('Avis clients'))

@section('content')
<div class="grid g3">
    <a class="stat" href="{{ route('tagtoa.reviews.index', ['status' => 'pending']) }}" style="display:block">
        <div class="ic" style="background:#fff5e6;color:#7a5200"><i class="fa-solid fa-clock"></i></div>
        <div class="v">{{ $counts['pending'] }}</div><div class="k">{{ __('En attente') }}</div>
    </a>
    <a class="stat" href="{{ route('tagtoa.reviews.index', ['status' => 'approved']) }}" style="display:block">
        <div class="ic" style="background:#eafaf3;color:#0e5f44"><i class="fa-solid fa-circle-check"></i></div>
        <div class="v">{{ $counts['approved'] }}</div><div class="k">{{ __('Publié') }}</div>
    </a>
    <a class="stat" href="{{ route('tagtoa.reviews.index', ['status' => 'rejected']) }}" style="display:block">
        <div class="ic" style="background:#fdecea;color:#9a2820"><i class="fa-solid fa-ban"></i></div>
        <div class="v">{{ $counts['rejected'] }}</div><div class="k">{{ __('Rejeté') }}</div>
    </a>
</div>

<div class="h-row" style="margin-top:22px">
    <h2>{{ __('Avis clients') }}</h2>
    @if($status)<a href="{{ route('tagtoa.reviews.index') }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-xmark"></i> {{ __('Tous') }}</a>@endif
</div>

@if($reviews->isEmpty())
    <div class="card"><div class="empty"><i class="fa-solid fa-star"></i>{{ __('Aucun avis pour le moment.') }}</div></div>
@else
    @foreach($reviews as $rv)
        @php $sm = $rv->status_meta; $subj = \Modules\Tagtoa\App\Models\Review\Review::SUBJECTS[$rv->subject_type] ?? null; @endphp
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px">
                <div>
                    <span style="color:var(--amber)">
                        @for($i=1;$i<=5;$i++)<i class="fa-solid fa-star" style="font-size:13px;color:{{ $i<=$rv->stars ? 'var(--amber)' : 'var(--bd)' }}"></i>@endfor
                    </span>
                    <span class="pill {{ $sm['pill'] }}">{{ __($sm['label']) }}</span>
                    @if($subj)<span class="pill n">{{ __($subj['label']) }}@if($rv->subject_alias) · {{ $rv->subject_alias }}@endif</span>@endif
                    <div style="font-family:var(--fh);font-weight:600;margin-top:6px">{{ $rv->author_name }}
                        <span style="color:var(--muted);font-weight:400;font-size:13px">· {{ $rv->created_at?->format('d/m/Y') }}@if($rv->author_phone) · {{ $rv->author_phone }}@endif</span>
                    </div>
                </div>
            </div>
            @if($rv->comment)<p style="margin-top:8px;font-size:14.5px">{{ $rv->comment }}</p>@endif
            @if($rv->reply)<div style="margin-top:10px;padding:10px 12px;border-left:3px solid var(--blue);background:var(--blue-pale);border-radius:8px;font-size:13.5px"><b>{{ __('Réponse du marchand') }} :</b> {{ $rv->reply }}</div>@endif

            <div class="row" style="margin-top:14px;gap:8px">
                @if($rv->status !== 'approved')
                    <form method="POST" action="{{ route('tagtoa.reviews.status',$rv->id) }}" style="flex:0">@csrf<input type="hidden" name="status" value="approved"><button class="btn btn-p btn-sm"><i class="fa-solid fa-check"></i> {{ __('Publier') }}</button></form>
                @endif
                @if($rv->status !== 'rejected')
                    <form method="POST" action="{{ route('tagtoa.reviews.status',$rv->id) }}" style="flex:0">@csrf<input type="hidden" name="status" value="rejected"><button class="btn btn-o btn-sm"><i class="fa-solid fa-ban"></i> {{ __('Rejeter') }}</button></form>
                @endif
                <form method="POST" action="{{ route('tagtoa.reviews.destroy',$rv->id) }}" onsubmit="return confirm('{{ __('Supprimer cet avis ?') }}')" style="flex:0">@csrf @method('DELETE')<button class="btn btn-o btn-sm" style="color:var(--red)"><i class="fa-solid fa-trash"></i></button></form>
            </div>

            <form method="POST" action="{{ route('tagtoa.reviews.reply',$rv->id) }}" style="margin-top:12px;display:flex;gap:8px;align-items:flex-end">
                @csrf
                <div style="flex:1"><label class="lbl">{{ __('Répondre publiquement') }}</label><input class="inp" name="reply" maxlength="1000" value="{{ $rv->reply }}" placeholder="{{ __('Votre réponse (optionnel)') }}"></div>
                <button class="btn btn-d btn-sm" style="flex:0"><i class="fa-solid fa-reply"></i> {{ __('Enregistrer') }}</button>
            </form>
        </div>
    @endforeach
    <div style="margin-top:16px">{{ $reviews->links() }}</div>
@endif
@endsection
