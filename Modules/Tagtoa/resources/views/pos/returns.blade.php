@extends('tagtoa::layouts.dashboard')
@section('title', __('Retours'))
@section('page', __('Retours'))

@push('head')
<style>
.rl{display:flex;gap:11px;align-items:center;padding:12px 0}
.rl + .rl{border-top:1px solid var(--bd)}
.rl .ic2{width:38px;height:38px;border-radius:11px;flex:0 0 38px;display:flex;align-items:center;
         justify-content:center;background:#fdecea;color:#9a2820;font-size:15px}
.rl .corps{flex:1;min-width:0}
.rl .corps b{font:700 14px var(--fh);display:block}
.rl .corps span{font-size:12.5px;color:var(--muted);display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.rl .arg{font:700 15px var(--fh);color:#9a2820;flex:0 0 auto}
.vente{display:flex;gap:10px;align-items:center;padding:10px 0}
.vente + .vente{border-top:1px solid var(--bd)}
</style>
@endpush

@section('content')
<div class="card" style="border-left:4px solid var(--amber)">
    <b style="font-family:var(--fh,sans-serif)">
        <i class="fa-solid fa-circle-info" style="color:var(--amber)"></i> {{ __('Un retour ne modifie jamais la vente') }}
    </b>
    <p style="color:var(--muted);font-size:13.5px;margin-top:6px;max-width:72ch">
        {{ __('Il s\'enregistre comme un document à part, daté, qui pointe vers le ticket d\'origine. Le ticket déjà remis au client reste valable, et votre rapport de journée reste vrai.') }}
    </p>
</div>

<div class="card">
    <div class="h-row"><h2>{{ __('Rendre un article') }}</h2></div>
    <p style="color:var(--muted);font-size:12.5px;margin:-6px 0 8px">
        {{ __('Choisissez le ticket concerné.') }}
        <a href="{{ route('tagtoa.pos.tickets') }}" style="color:var(--blue-deep);font-weight:700">{{ __('Chercher un ticket plus ancien') }}</a>
    </p>

    @forelse($recents as $s)
        <div class="vente">
            <span style="flex:1;min-width:0">
                <b style="font:700 14px var(--fh)">{{ $s->reference }}</b>
                <span style="color:var(--muted);font-size:12.5px"> · {{ optional($s->sold_at)->format('d/m H:i') }}</span>
            </span>
            <span style="font:700 14px var(--fh)">{{ \Modules\Tagtoa\App\Support\Money::format($s->total, $s->currency) }}</span>
            <a class="btn btn-o btn-sm" href="{{ route('tagtoa.pos.returns.create', $s->id) }}">
                <i class="fa-solid fa-rotate-left"></i> {{ __('Retour') }}
            </a>
        </div>
    @empty
        <div class="empty" style="padding:26px 16px">
            <i class="fa-solid fa-receipt"></i> {{ __('Aucune vente encore.') }}
        </div>
    @endforelse
</div>

<div class="card">
    <div class="h-row"><h2>{{ __('Retours enregistrés') }}</h2></div>
    @forelse($returns as $r)
        <div class="rl">
            <span class="ic2"><i class="fa-solid fa-rotate-left"></i></span>
            <span class="corps">
                <b>{{ $r->reference }}</b>
                <span>
                    {{ optional($r->returned_at)->format('d/m/Y H:i') }}
                    · {{ __('ticket') }} {{ $r->sale->reference ?? '—' }}
                    · {{ __($r->kind_label) }}
                    @unless($r->restocked) · {{ __('non remis en stock') }} @endunless
                </span>
            </span>
            <span class="arg">−{{ \Modules\Tagtoa\App\Support\Money::format($r->total, $r->currency) }}</span>
        </div>
    @empty
        <div class="empty" style="padding:26px 16px">
            <i class="fa-solid fa-rotate-left"></i> {{ __('Aucun retour. Tant mieux.') }}
        </div>
    @endforelse
    @if($returns->hasPages())<div style="margin-top:14px">{{ $returns->links() }}</div>@endif
</div>
@endsection
