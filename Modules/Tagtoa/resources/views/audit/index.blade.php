@extends('tagtoa::layouts.dashboard')
@section('title', __('Journal d\'audit'))
@section('page', __('Journal d\'audit'))

@section('content')
<div class="card">
    <div class="h-row">
        <h2>{{ __('Actions sensibles') }}</h2>
        <form method="GET" style="flex:0">
            <select class="sel" name="action" onchange="this.form.submit()" style="min-width:220px">
                <option value="">{{ __('Toutes les actions') }}</option>
                @foreach($actions as $key => $label)
                    <option value="{{ $key }}" @selected($action===$key)>{{ __($label) }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if($logs->isEmpty())
        <div class="empty"><i class="fa-solid fa-clipboard-list"></i>{{ __('Aucune action enregistrée pour le moment.') }}</div>
    @else
        <table>
            <thead><tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Action') }}</th>
                <th>{{ __('Utilisateur') }}</th>
                <th>{{ __('Détail') }}</th>
            </tr></thead>
            <tbody>
            @foreach($logs as $log)
                <tr>
                    <td style="white-space:nowrap;color:var(--muted)">{{ optional($log->created_at)->format('d/m/Y H:i') }}</td>
                    <td><span class="pill n">{{ __($log->action_label) }}</span></td>
                    <td>{{ $log->user_name ?: '—' }}</td>
                    <td style="color:var(--muted)">{{ $log->description ?: ($log->subject_type ? $log->subject_type.' #'.$log->subject_id : '') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div style="margin-top:16px">{{ $logs->links() }}</div>
    @endif
</div>
@endsection
