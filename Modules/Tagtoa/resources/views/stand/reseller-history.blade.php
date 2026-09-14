@extends('tagtoa::layouts.dashboard')
@section('title', $stand->public_id)
@section('page', $stand->public_id)

@section('content')
<div class="h-row">
    <a href="{{ route('tagtoa.reseller.index') }}" style="color:var(--muted);font-size:14px">
        <i class="fa-solid fa-arrow-left"></i> {{ __('Mon stock') }}
    </a>
</div>

<div class="card">
    <div class="h-row" style="margin-bottom:10px">
        <h2>{{ __('Histoire de ce stand') }}</h2>
        <span class="pill n">{{ __($stand->physical_label) }}</span>
        <span class="pill n">{{ __($stand->digital_label) }}</span>
    </div>

    {{-- Écrite une fois, jamais modifiée : c'est ce qui permet de trancher un
         litige — qui l'a eu, quand, et par qui il a été activé. --}}
    @forelse($events as $ev)
        <div style="display:flex;gap:11px;padding:10px 0;{{ $loop->first ? '' : 'border-top:1px solid var(--bd)' }}">
            <span style="flex:0 0 38px;height:38px;border-radius:11px;background:var(--blue-pale);color:var(--blue-deep);display:flex;align-items:center;justify-content:center">
                <i class="fa-solid fa-circle-dot"></i>
            </span>
            <span style="flex:1;min-width:0">
                <b style="font:700 14px var(--fh);display:block">{{ $ev->event }}</b>
                <span style="font-size:12.5px;color:var(--muted)">
                    {{ optional($ev->created_at)->format('d/m/Y H:i') }}
                    @if($ev->actor_name) · {{ $ev->actor_name }} @endif
                    @if($ev->from_state) · {{ $ev->from_state }} → {{ $ev->to_state }} @endif
                </span>
            </span>
        </div>
    @empty
        <div class="empty" style="padding:26px 16px">{{ __('Aucun événement.') }}</div>
    @endforelse
</div>
@endsection
