@extends('tagtoa::layouts.dashboard')
@section('title', __('Staff terrain'))
@section('page', __('Staff terrain') . ' — ' . $event->title)

@section('content')

{{-- Accès terminal + export --}}
<div class="card" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <div style="flex:1;min-width:220px">
        <b style="font-family:var(--fh)">{{ __('Terminal staff (terrain)') }}</b>
        <div style="color:var(--muted);font-size:13px">{{ __('Partagez ce lien avec votre équipe — connexion par PIN, aucun mot de passe.') }}</div>
        <code style="font-size:12.5px">{{ route('tagtoa.event.staff.terminal', $event->alias) }}</code>
    </div>
    <a class="btn btn-p" target="_blank" href="{{ route('tagtoa.event.staff.terminal', $event->alias) }}"><i class="fa-solid fa-mobile-screen"></i> {{ __('Ouvrir le terminal') }}</a>
    <a class="btn btn-o" href="{{ route('tagtoa.event.dashboard.staff.export', $event->id) }}"><i class="fa-solid fa-file-csv"></i> {{ __('Export CSV') }}</a>
</div>

{{-- Création d'un compte staff --}}
<div class="card" style="margin-top:16px">
    <div class="h-row"><h2>{{ __('Ajouter un membre du staff') }}</h2>
        @if($limit !== null)<span class="pill n">{{ $staff->count() }}/{{ $limit }}</span>@endif
    </div>
    @if($canAdd)
    <form method="POST" action="{{ route('tagtoa.event.dashboard.staff.store', $event->id) }}">@csrf
        <div class="row">
            <div><label class="lbl">{{ __('Nom') }} *</label><input class="inp" name="name" required maxlength="120"></div>
            <div><label class="lbl">{{ __('Rôle') }} *</label>
                <select class="sel" name="role">
                    <option value="checkin">{{ __('Check-in (portes)') }}</option>
                    <option value="vente">{{ __('Vente (cartes/billets)') }}</option>
                    <option value="admin">{{ __('Admin terrain') }}</option>
                </select>
            </div>
            <div><label class="lbl">{{ __('PIN (4 à 6 chiffres)') }} *</label><input class="inp" name="pin" required inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" placeholder="••••"></div>
        </div>
        <button class="btn btn-p" style="margin-top:12px"><i class="fa-solid fa-user-plus"></i> {{ __('Créer le compte') }}</button>
    </form>
    @else
        <p style="color:var(--muted)">{{ __('Limite de staff atteinte pour votre forfait.') }} <a href="{{ url('/tagtoa/plan') }}" style="color:var(--blue-deep);font-weight:700">{{ __('Changer de forfait') }}</a></p>
    @endif
</div>

{{-- Liste du staff --}}
<div class="card" style="margin-top:16px">
    <div class="h-row"><h2>{{ __('Équipe') }} ({{ $staff->count() }})</h2></div>
    @if($staff->isEmpty())
        <div class="empty"><i class="fa-solid fa-users"></i>{{ __('Aucun membre pour l\'instant. Créez le premier compte ci-dessus.') }}</div>
    @else
    <div style="overflow-x:auto"><table>
        <tr><th>{{ __('Nom') }}</th><th>{{ __('Rôle') }}</th><th>{{ __('Check-ins') }}</th><th>{{ __('Dernière connexion') }}</th><th>{{ __('Statut') }}</th><th></th></tr>
        @foreach($staff as $s)
        <tr>
            <td><b>{{ $s->name }}</b></td>
            <td><span class="pill {{ $s->role === 'admin' ? 'a' : 'n' }}">{{ $s->role }}</span></td>
            <td>{{ $activity[$s->id] ?? 0 }}</td>
            <td>{{ optional($s->last_login_at)->format('d/m H:i') ?: '—' }}</td>
            <td>{!! $s->active ? '<span class="pill g">'.__('Actif').'</span>' : '<span class="pill r">'.__('Inactif').'</span>' !!}</td>
            <td style="white-space:nowrap">
                <form method="POST" action="{{ route('tagtoa.event.dashboard.staff.toggle', [$event->id, $s->id]) }}" style="display:inline">@csrf
                    <button class="btn btn-o btn-sm">{{ $s->active ? __('Désactiver') : __('Activer') }}</button>
                </form>
                <form method="POST" action="{{ route('tagtoa.event.dashboard.staff.pin', [$event->id, $s->id]) }}" style="display:inline-flex;gap:6px;align-items:center">@csrf
                    <input class="inp" name="pin" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" placeholder="{{ __('Nouveau PIN') }}" style="width:110px;padding:7px 10px">
                    <button class="btn btn-d btn-sm">{{ __('Réinitialiser') }}</button>
                </form>
            </td>
        </tr>
        @endforeach
    </table></div>
    @endif
</div>

{{-- Conflits de synchronisation --}}
<div class="card" style="margin-top:16px">
    <div class="h-row"><h2>{{ __('Conflits de synchronisation') }}</h2>
        <span class="pill {{ $conflicts->where('resolved', false)->count() ? 'a' : 'g' }}">{{ $conflicts->where('resolved', false)->count() }} {{ __('non résolus') }}</span>
    </div>
    @if($conflicts->isEmpty())
        <p style="color:var(--muted);font-size:14px">{{ __('Aucun conflit — les appareils sont synchronisés.') }}</p>
    @else
    <div style="overflow-x:auto"><table>
        <tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Billet') }}</th><th>{{ __('Staff') }}</th><th>{{ __('Statut') }}</th><th></th></tr>
        @foreach($conflicts as $c)
        <tr>
            <td>{{ $c->created_at->format('d/m H:i') }}</td>
            <td>{{ $c->kind === 'duplicate_checkin' ? __('Double check-in') : $c->kind }}</td>
            <td>{{ optional($c->ticket)->code ?: '—' }} <span style="color:var(--muted)">{{ optional($c->ticket)->holder_name }}</span></td>
            <td>{{ optional($c->staff)->name ?: '—' }}</td>
            <td>{!! $c->resolved ? '<span class="pill g">'.__('Résolu').'</span>' : '<span class="pill a">'.__('À vérifier').'</span>' !!}</td>
            <td>
                @unless($c->resolved)
                <form method="POST" action="{{ route('tagtoa.event.dashboard.staff.conflict.resolve', [$event->id, $c->id]) }}">@csrf
                    <button class="btn btn-o btn-sm">{{ __('Marquer résolu') }}</button>
                </form>
                @endunless
            </td>
        </tr>
        @endforeach
    </table></div>
    @endif
</div>
@endsection
