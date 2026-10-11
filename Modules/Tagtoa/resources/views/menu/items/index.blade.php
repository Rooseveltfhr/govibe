@extends('tagtoa::layouts.dashboard')
@section('title', __('Produits — :menu', ['menu' => $menu->name]))
@section('page', __('Produits'))

@section('content')
<div class="h-row">
    <h2>{{ $menu->name }} — {{ __('Produits') }}</h2>
    <div style="display:flex;gap:8px">
        <a href="{{ route('tagtoa.menu.dashboard.edit', $menu->id) }}" class="btn btn-o btn-sm"><i class="fa-solid fa-store"></i> {{ __('Établissement & menu') }}</a>
        <a href="{{ route('tagtoa.menu.dashboard.items.create', $menu->id) }}" class="btn btn-p btn-sm"><i class="fa-solid fa-plus"></i> {{ __('Ajouter un produit') }}</a>
    </div>
</div>

@if($menu->categories->isEmpty())
    <div class="card"><div class="empty">
        <i class="fa-solid fa-layer-group"></i>
        {{ __('Aucune catégorie pour l\'instant. Le premier produit que vous ajoutez peut en créer une.') }}
        <div style="margin-top:16px">
            <a href="{{ route('tagtoa.menu.dashboard.items.create', $menu->id) }}" class="btn btn-p"><i class="fa-solid fa-plus"></i> {{ __('Ajouter un produit') }}</a>
        </div>
    </div></div>
@else
    @foreach($menu->categories as $cat)
        <div class="card" style="margin-top:14px">
            <div class="h-row">
                <h3 style="font-size:15px"><i class="{{ \Modules\Tagtoa\App\Support\Menu\CategoryIcon::resolve($cat->icon, $cat->name) }}"></i> {{ $cat->name }}
                    <span style="color:var(--muted);font-weight:400;font-size:13px">({{ $cat->items->count() }})</span>
                </h3>
            </div>
            @if($cat->items->isEmpty())
                <p style="color:var(--muted);font-size:13.5px">{{ __('Aucun produit dans cette catégorie.') }}</p>
            @else
                <div class="grid g2">
                    @foreach($cat->items as $it)
                        <div class="card" id="item-{{ $it->id }}" style="padding:10px;display:flex;gap:10px;align-items:center;{{ (session('highlight_item') == $it->id) ? 'border-color:#2cb809;background:rgba(44,184,9,.06)' : '' }}">
                            @if($it->image_url)
                                <img src="{{ $it->image_url }}" alt="" style="width:48px;height:48px;border-radius:10px;object-fit:cover;flex:0 0 auto">
                            @else
                                <div style="width:48px;height:48px;border-radius:10px;background:var(--blue-pale);color:var(--blue-deep);display:flex;align-items:center;justify-content:center;font-weight:700;flex:0 0 auto">{{ mb_strtoupper(mb_substr($it->name, 0, 1)) }}</div>
                            @endif
                            <div style="flex:1;min-width:0">
                                <b style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $it->name }}</b>
                                <span style="font-size:13px;color:var(--muted)">{{ \Modules\Tagtoa\App\Support\Money::format($it->price, $menu->currency) }}</span>
                                @unless($it->is_available)<span class="pill n" style="margin-left:6px">{{ __('Indisponible') }}</span>@endunless
                            </div>
                            <div style="display:flex;gap:6px;flex:0 0 auto">
                                <a href="{{ route('tagtoa.menu.dashboard.items.edit', [$menu->id, $it->id]) }}" class="btn btn-o btn-sm" title="{{ __('Modifier') }}"><i class="fa-solid fa-pen"></i></a>
                                <form method="POST" action="{{ route('tagtoa.menu.dashboard.items.destroy', [$menu->id, $it->id]) }}" onsubmit="return confirm('{{ __('Supprimer ce produit ?') }}')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-o btn-sm" style="color:var(--red)" title="{{ __('Supprimer') }}"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
@endif
@endsection
