@php
    // Mesaj WhatsApp la deja ekri: kliyan an peze yon sèl bouton. Se li ki
    // pouse mesaj la — okenn sèvè pa ka ekri nan WhatsApp san yon nimewo
    // Cloud API, epi nou p ap fè sanblan.
    $lines = [
        __('Demande LOUVIA').' '.$order->reference,
        __('Modèle').': '.($template ? $template->label : $order->sector),
        __('Entreprise').': '.$order->business_name,
        __('Mise en place').': '.($order->wantsExpert() ? __('par un expert GOVIBE') : __('par le client')),
        __('Canaux').': '.implode(', ', $order->channels ?? []),
    ];

    if (trim((string) $order->notes) !== '') {
        $lines[] = __('Besoin').': '.$order->shortNotes();
    }

    $waUrl = 'https://wa.me/50933988754?text='.rawurlencode(implode("\n", $lines));
@endphp

<x-agents::layouts.app :title="__('Demande enregistrée')">

    <div class="page-head">
        <h1>{{ __('Demande enregistrée') }}</h1>
        <p class="lead">
            {{ __('Gardez cette référence : elle nous permet de retrouver votre dossier.') }}
        </p>
    </div>

    <div class="note ok">
        <strong>{{ $order->reference }}</strong>
    </div>

    <h2>{{ __('Votre demande') }}</h2>
    <table>
        <tr><td>{{ __('Modèle') }}</td><td>{{ $template ? $template->label : $order->sector }}</td></tr>
        <tr><td>{{ __('Entreprise') }}</td><td>{{ $order->business_name }}</td></tr>
        <tr><td>{{ __('WhatsApp') }}</td><td>{{ $order->whatsapp }}</td></tr>
        <tr>
            <td>{{ __('Mise en place') }}</td>
            <td>{{ $order->wantsExpert() ? __('Un expert GOVIBE monte l’agent') : __('Vous créez l’agent vous-même') }}</td>
        </tr>
        <tr><td>{{ __('Canaux') }}</td><td>{{ implode(', ', $order->channels ?? []) ?: '—' }}</td></tr>
        @if (trim((string) $order->notes) !== '')
            <tr><td>{{ __('Besoin') }}</td><td>{{ $order->notes }}</td></tr>
        @endif
    </table>

    <h2>{{ __('Étape suivante') }}</h2>
    <p class="lead">
        {{ __("La demande est déjà enregistrée chez nous. Le message WhatsApp ci-dessous prévient l'équipe tout de suite — rien n'est perdu si vous ne l'envoyez pas.") }}
    </p>

    <div class="row" style="margin-top:1rem">
        <a class="btn btn-primary" href="{{ $waUrl }}" target="_blank" rel="noopener">
            {{ __('Prévenir GOVIBE sur WhatsApp') }}
        </a>
        @if (! $order->wantsExpert() && $template)
            <a class="btn" href="{{ route('agents.create', $order->sector) }}">{{ __('Créer mon agent maintenant') }}</a>
        @endif
        <a class="btn" href="{{ route('agents.demo', $order->sector) }}">{{ __('Essayer le modèle') }}</a>
    </div>

    <p class="back"><a href="{{ route('home') }}">{{ __('Accueil') }}</a></p>

</x-agents::layouts.app>
