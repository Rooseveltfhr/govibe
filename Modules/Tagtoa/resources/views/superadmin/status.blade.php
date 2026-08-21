@extends('tagtoa::layouts.dashboard')
@section('title', __('État système'))
@section('page', __('Super-admin — état système'))

@section('content')
{{-- Lecture seule. Aucun secret affiché : uniquement des booléens et des noms de driver. --}}
<p style="color:var(--muted,#888);font-size:13.5px;margin-top:-8px;margin-bottom:16px">
    {{ __('Cette page existe pour ne rien cacher : ce qui tourne réellement, où, et ce qui n\'est pas encore branché.') }}
</p>

<div class="card" style="margin-bottom:16px">
    <div class="h-row"><h2><i class="fa-solid fa-server"></i> {{ __('Environnement') }}</h2></div>
    <div class="kv"><span>{{ __('Hôte utilisé pour joindre cette page') }}</span><b>{{ $environment['request_host'] }}</b></div>
    <div class="kv"><span>{{ __('APP_URL configurée') }}</span><b>{{ $environment['app_url'] }}</b></div>
    <div class="kv"><span>{{ __('Environnement Laravel') }}</span><b>{{ $environment['app_env'] }}</b></div>
    <div class="kv"><span>{{ __('Hôte serveur') }}</span><b>{{ $environment['server_host'] ?: '—' }}</b></div>
    <div class="kv"><span>PHP</span><b>{{ $environment['php_version'] }}</b></div>
    <div class="kv"><span>Laravel</span><b>{{ $environment['laravel'] }}</b></div>
    <div class="kv"><span>{{ __('Fuseau horaire / langue') }}</span><b>{{ $environment['timezone'] }} · {{ $environment['locale'] }}</b></div>
    @if(in_array($environment['app_env'], ['local', 'testing'], true) || str_contains($environment['request_host'] ?? '', 'localhost'))
        <p style="color:#9a2820;font-size:13px;margin-top:8px"><i class="fa-solid fa-triangle-exclamation"></i> {{ __('Ceci ressemble à un environnement local/test, pas à la production.') }}</p>
    @else
        <p style="color:#1b5e12;font-size:13px;margin-top:8px"><i class="fa-solid fa-circle-check"></i> {{ __('Cette page est servie depuis un environnement réel, pas localhost.') }}</p>
    @endif
</div>

<div class="grid g2" style="gap:16px;margin-bottom:16px">
    <div class="card">
        <div class="h-row"><h2><i class="fa-solid fa-database"></i> {{ __('Base de données') }}</h2></div>
        <div class="kv"><span>{{ __('Connexion') }}</span><b>{{ $database['connection'] }} ({{ $database['driver'] }})</b></div>
        <div class="kv"><span>{{ __('Statut') }}</span><b style="color:{{ $database['connected'] ? '#1b5e12' : '#9a2820' }}">{{ $database['connected'] ? __('Connectée') : __('Erreur') }}</b></div>
        @if(!$database['connected'] && $database['error'])
            <p style="color:#9a2820;font-size:12.5px;margin-top:6px">{{ Str::limit($database['error'], 160) }}</p>
        @endif
    </div>
    <div class="card">
        <div class="h-row"><h2><i class="fa-solid fa-bolt"></i> {{ __('Cache') }}</h2></div>
        <div class="kv"><span>{{ __('Pilote') }}</span><b>{{ $cache['driver'] }}</b></div>
        <div class="kv"><span>{{ __('Test écriture/lecture') }}</span><b style="color:{{ $cache['working'] ? '#1b5e12' : '#9a2820' }}">{{ $cache['working'] ? __('Fonctionne') : __('Échec') }}</b></div>
    </div>
</div>

<div class="grid g2" style="gap:16px;margin-bottom:16px">
    <div class="card">
        <div class="h-row"><h2><i class="fa-solid fa-list-check"></i> {{ __('File d\'attente (queue)') }}</h2></div>
        <div class="kv"><span>{{ __('Pilote') }}</span><b>{{ $queue['driver'] }}</b></div>
        <p style="color:var(--muted,#888);font-size:12.5px;margin-top:6px">{{ $queue['note'] }}</p>
    </div>
    <div class="card">
        <div class="h-row"><h2><i class="fa-solid fa-comment"></i> {{ __('Notifications') }}</h2></div>
        <div class="kv"><span>E-mail</span><b>{{ $notifications['email_enabled'] ? __('Activé') : __('Désactivé') }}</b></div>
        <div class="kv"><span>WhatsApp</span><b>{{ $notifications['whatsapp_enabled'] ? __('Activé') : __('Désactivé') }}</b></div>
        <div class="kv"><span>{{ __('Identifiants WhatsApp') }}</span><b>{{ $notifications['whatsapp_configured'] ? __('Configurés') : __('Manquants') }}</b></div>
    </div>
</div>

<div class="card" style="margin-bottom:16px">
    <div class="h-row"><h2><i class="fa-solid fa-shield-halved"></i> {{ __('Sécurité NFC anti-clonage (NTAG424)') }}</h2></div>
    <div class="kv"><span>{{ __('Drapeau de configuration') }}</span><b>{{ $nfc['flag_enabled'] ? __('Activé') : __('Désactivé') }}</b></div>
    <div class="kv"><span>{{ __('Clé configurée') }}</span><b>{{ $nfc['has_key'] ? __('Oui') : __('Non') }}</b></div>
    <div class="kv"><span>{{ __('Branché dans le check-in en production') }}</span><b style="color:#9a2820">{{ __('Non') }}</b></div>
    <p style="color:#9a2820;font-size:13px;margin-top:8px"><i class="fa-solid fa-triangle-exclamation"></i> {{ $nfc['note'] }}</p>
</div>

<div class="card" style="margin-bottom:16px">
    <div class="h-row"><h2><i class="fa-solid fa-chart-simple"></i> {{ __('Données réelles (compteurs bruts)') }}</h2></div>
    <div class="kv"><span>{{ __('Menus créés') }}</span><b>{{ $usage['menus'] ?? '—' }}</b></div>
    <div class="kv"><span>{{ __('Événements créés') }}</span><b>{{ $usage['events'] ?? '—' }}</b></div>
    <div class="kv"><span>{{ __('Pages de paiement') }}</span><b>{{ $usage['pay_pages'] ?? '—' }}</b></div>
    <div class="kv"><span>{{ __('Cartes fidélité') }}</span><b>{{ $usage['loyalty_cards'] ?? '—' }}</b></div>
</div>

<div class="card">
    <div class="h-row"><h2><i class="fa-solid fa-circle-info"></i> {{ __('Limites connues') }}</h2></div>
    <ul style="margin:0;padding-left:20px;font-size:14px;color:var(--muted,#555);line-height:1.7">
        <li>{{ __('Un seul serveur (VPS) — pas de répartiteur de charge ni de mise à l\'échelle automatique.') }}</li>
        <li>{{ __('Aucun test de charge automatisé n\'a encore été exécuté contre la production (script disponible : Modules/Tagtoa/scripts/loadtest.sh).') }}</li>
        <li>{{ __('Le socle Biztap (cœur du logiciel) n\'est pas dans ce dépôt — TAGTOA ne peut pas en garantir le comportement interne.') }}</li>
        <li>{{ __('Aucun bêta-test prolongé avec de vrais marchands n\'a encore été mené — la correction du code est testée, pas le comportement des vrais utilisateurs.') }}</li>
    </ul>
</div>
@endsection
