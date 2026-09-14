@extends('tagtoa::layouts.dashboard')
@section('title', __('Tickets'))
@section('page', __('Tickets / Reçus'))

@push('head')
<style>
.tk{display:flex;gap:11px;align-items:center;padding:12px 0}
.tk + .tk{border-top:1px solid var(--bd)}
.tk .ic2{width:38px;height:38px;border-radius:11px;flex:0 0 38px;display:flex;align-items:center;
         justify-content:center;background:var(--blue-pale);color:var(--blue-deep);font-size:15px}
.tk .corps{flex:1;min-width:0}
.tk .corps b{font:700 14px var(--fh);display:block}
.tk .corps span{font-size:12.5px;color:var(--muted);display:block;overflow:hidden;
                text-overflow:ellipsis;white-space:nowrap}
.tk .arg{text-align:right;flex:0 0 auto;font:700 15px var(--fh)}
</style>
@endpush

@section('content')
<div class="card">
    <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
        <input class="inp" name="q" value="{{ $filtres['q'] ?? '' }}" style="flex:1;min-width:150px"
               placeholder="{{ __('N° de ticket ou téléphone…') }}">
        <input class="inp" type="date" name="date" value="{{ $filtres['date'] ?? '' }}" style="flex:0 0 auto;width:auto">
        <button class="btn btn-d"><i class="fa-solid fa-magnifying-glass"></i></button>
    </form>

    @forelse($sales as $s)
        <a class="tk" href="{{ route('tagtoa.pos.ticket', $s->id) }}" target="_blank" rel="noopener">
            <span class="ic2"><i class="fa-solid fa-receipt"></i></span>
            <span class="corps">
                <b>{{ $s->reference }}</b>
                <span>
                    {{ optional($s->sold_at)->format('d/m/Y H:i') }}
                    · {{ $caisses[$s->terminal_id] ?? __('Caisse') }}
                    @if($s->staff) · {{ $s->staff->name }} @endif
                    · {{ trans_choice('{1}:count article|[2,*]:count articles', $s->items->count(), ['count' => $s->items->count()]) }}
                </span>
            </span>
            <span class="arg">{{ \Modules\Tagtoa\App\Support\Money::format($s->total, $s->currency) }}</span>
        </a>
    @empty
        <div class="empty" style="padding:34px 16px">
            <i class="fa-solid fa-receipt"></i>
            {{ __('Aucun ticket pour cette recherche.') }}
        </div>
    @endforelse

    @if($sales->hasPages())<div style="margin-top:14px">{{ $sales->links() }}</div>@endif
</div>
@endsection
