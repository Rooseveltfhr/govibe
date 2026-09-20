@extends('tagtoa::layouts.dashboard')
@section('title', __('Tables'))
@section('page', __('Tables') . ' — ' . $menu->name)

{{--
    TAGTOA MENU — tables vérifiées par QR/NFC.

    `table_label` sur une commande était du texte libre tapé par le client :
    rien n'empêchait d'écrire n'importe quel numéro, volontairement ou par
    erreur. Une table créée ici porte un code que SEUL le QR imprimé et posé
    dessus connaît — le client le scanne, il ne le tape jamais.
--}}

@section('content')
<div class="h-row">
    <a href="{{ route('tagtoa.menu.dashboard.edit',$menu->id) }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>
    <h2 style="flex:1">{{ __('Tables') }} — {{ $menu->name }}</h2>
</div>

<div class="card" style="border-left:4px solid #2cb809">
    <p style="color:var(--muted);font-size:13.5px">
        {{ __('Créez une table, imprimez son QR, posez-le dessus. Un client qui scanne ce QR précis commande directement pour CETTE table — il ne tape jamais de numéro, donc jamais le mauvais.') }}
    </p>
</div>

<div class="card" style="margin-top:14px">
    <div class="h-row"><h2>{{ __('Nouvelle table') }}</h2></div>
    <form method="POST" action="{{ route('tagtoa.menu.dashboard.tables.store',$menu->id) }}" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        @csrf
        <div style="flex:1;min-width:160px">
            <label class="lbl">{{ __('Nom / numéro') }}</label>
            <input class="inp" name="label" maxlength="40" required placeholder="{{ __('Ex. Table 4, Terrasse 2…') }}">
        </div>
        <button class="btn btn-p"><i class="fa-solid fa-plus"></i> {{ __('Ajouter') }}</button>
    </form>
    @error('label')<div style="color:var(--red);font-size:13px;margin-top:8px">{{ $message }}</div>@enderror
</div>

<div class="h-row" style="margin-top:26px"><h2>{{ __('Tables enregistrées') }} <span style="color:var(--muted);font-weight:400">({{ $tables->count() }})</span></h2></div>

@if($tables->isEmpty())
    <div class="card" style="text-align:center;padding:34px">
        <i class="fa-solid fa-chair" style="font-size:30px;color:var(--muted);opacity:.5"></i>
        <p style="color:var(--muted);margin-top:12px">{{ __('Aucune table pour l\'instant. Sans elles, le client tape son numéro lui-même — ce qui reste possible.') }}</p>
    </div>
@else
    <div class="grid g3">
        @foreach($tables as $t)
            <div class="card" style="text-align:center">
                <b style="font-family:var(--fh,sans-serif);font-size:16px">{{ $t->label }}</b>
                <div style="margin:10px 0">
                    @php $svg = \Modules\Tagtoa\App\Support\Qr::svg(url('/menu/'.$menu->alias).'?t='.$t->code, 140); @endphp
                    @if($svg){!! $svg !!}@else<img src="{{ \Modules\Tagtoa\App\Support\Qr::imgUrl(url('/menu/'.$menu->alias).'?t='.$t->code, 140) }}" width="140" height="140" alt="QR">@endif
                </div>
                <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap">
                    <a href="{{ route('tagtoa.menu.dashboard.tables.poster',[$menu->id,$t->id]) }}" target="_blank" class="btn btn-o btn-sm"><i class="fa-solid fa-print"></i> {{ __('Imprimer') }}</a>
                    <form method="POST" action="{{ route('tagtoa.menu.dashboard.tables.destroy',[$menu->id,$t->id]) }}" onsubmit="return confirm('{{ __('Supprimer cette table ?') }}')">
                        @csrf @method('DELETE')
                        <button class="btn btn-o btn-sm" style="color:var(--red)"><i class="fa-solid fa-trash"></i></button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
