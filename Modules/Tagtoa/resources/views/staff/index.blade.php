@extends('tagtoa::layouts.dashboard')
@section('title', __('Équipe'))
@section('page', __('Votre équipe'))

@section('content')
<div class="card" style="border-left:4px solid #2cb809;margin-bottom:16px">
    <b style="font-family:var(--fh,sans-serif)"><i class="fa-solid fa-circle-info" style="color:#2cb809"></i> {{ __('Comment ça marche') }}</b>
    <p style="color:var(--muted);font-size:13.5px;margin-top:6px;max-width:70ch">
        {{ __('Chaque personne reçoit un code à 4 chiffres. Elle le tape sur la caisse pour ouvrir son poste, et le retape pour les gestes qui engagent : une remise, un remboursement, un article retiré du catalogue.') }}
        <br>
        {{ __('Vous êtes le seul à voir l\'ensemble des caisses. Un caissier ne voit que ses propres ventes.') }}
    </p>
</div>

{{-- ---------- Nouvelle personne ---------- --}}
<div class="card">
    <div class="h-row"><h2>{{ __('Ajouter quelqu\'un') }}</h2></div>
    <form method="POST" action="{{ route('tagtoa.staff.store') }}">
        @csrf
        @include('tagtoa::staff.partials.fields', ['staff' => null])
        <button class="btn btn-p" style="margin-top:14px">
            <i class="fa-solid fa-user-plus"></i> {{ __('Enregistrer') }}
        </button>
    </form>
</div>

{{-- ---------- L'équipe ---------- --}}
<div class="h-row" style="margin-top:26px"><h2>{{ __('Personnes enregistrées') }} <span style="color:var(--muted);font-weight:400">({{ $roster->count() }})</span></h2></div>

@if($roster->isEmpty())
    <div class="card" style="text-align:center;padding:34px">
        <i class="fa-solid fa-users" style="font-size:30px;color:var(--muted);opacity:.5"></i>
        <p style="color:var(--muted);margin-top:12px">
            {{ __('Personne pour l\'instant. Tant que c\'est le cas, la caisse fonctionne sous votre seul nom.') }}
        </p>
    </div>
@else
    @foreach($roster as $m)
        <div class="card" style="margin-bottom:12px;{{ $m->is_active ? '' : 'opacity:.62' }}">
            <div style="display:flex;align-items:center;gap:13px;flex-wrap:wrap">
                <span class="who">{{ $m->initials }}</span>
                <div style="flex:1;min-width:180px">
                    <b style="font-family:var(--fh,sans-serif);font-size:16px">{{ $m->name }}</b>
                    <div style="color:var(--muted);font-size:13px;margin-top:2px">
                        {{ __($m->role_label) }}
                        @if($m->terminal) · {{ $m->terminal->name }} @endif
                        @if($m->last_login_at) · {{ __('vu le') }} {{ $m->last_login_at->format('d/m à H:i') }} @endif
                    </div>
                </div>
                <span class="pill {{ $m->is_active ? 'g' : 'n' }}">{{ $m->is_active ? __('Actif') : __('Accès fermé') }}</span>
            </div>

            {{-- Ce que cette personne peut faire, en clair. --}}
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:12px">
                @foreach(\Modules\Tagtoa\App\Support\Pos\StaffAccess::abilitiesFor($m->role) as $droit)
                    <span class="cap">{{ __(\Modules\Tagtoa\App\Support\Pos\StaffAccess::label($droit)) }}</span>
                @endforeach
            </div>

            <details style="margin-top:14px">
                <summary style="cursor:pointer;color:var(--blue-deep);font-weight:600;font-size:13.5px">
                    <i class="fa-solid fa-pen"></i> {{ __('Modifier') }}
                </summary>
                <form method="POST" action="{{ route('tagtoa.staff.update',$m->id) }}" style="margin-top:12px">
                    @csrf @method('PUT')
                    @include('tagtoa::staff.partials.fields', ['staff' => $m])
                    <button class="btn btn-p btn-sm" style="margin-top:12px"><i class="fa-solid fa-floppy-disk"></i> {{ __('Enregistrer') }}</button>
                </form>
            </details>

            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">
                <form method="POST" action="{{ route('tagtoa.staff.toggle',$m->id) }}">@csrf
                    <button class="btn btn-o btn-sm">
                        <i class="fa-solid {{ $m->is_active ? 'fa-lock' : 'fa-lock-open' }}"></i>
                        {{ $m->is_active ? __('Fermer l\'accès') : __('Rouvrir l\'accès') }}
                    </button>
                </form>
                <form method="POST" action="{{ route('tagtoa.staff.destroy',$m->id) }}"
                      onsubmit="return confirm('{{ __('Supprimer la fiche de') }} {{ $m->name }} ? {{ __('Ses ventes resteront dans vos rapports.') }}')">
                    @csrf @method('DELETE')
                    <button class="btn btn-o btn-sm" style="color:var(--red)"><i class="fa-solid fa-trash"></i> {{ __('Supprimer') }}</button>
                </form>
            </div>
        </div>
    @endforeach
@endif

<style>
    .who{width:44px;height:44px;border-radius:50%;background:var(--blue);color:#fff;flex:0 0 44px;
         display:inline-flex;align-items:center;justify-content:center;font:700 15px var(--fh,sans-serif)}
    .cap{font-size:11.5px;background:var(--blue-pale);color:var(--blue-deep);
         border-radius:999px;padding:3px 10px;font-weight:600}
    details summary::-webkit-details-marker{display:none}
</style>
@endsection
