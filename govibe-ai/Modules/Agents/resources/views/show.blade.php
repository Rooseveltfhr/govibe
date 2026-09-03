<x-agents::layouts.app :title="$agent->name">

    <div class="page-head">
        <h1>{{ $agent->name }}</h1>
        <p class="lead">
            <span class="tag">{{ $agent->sector }}</span>
            @foreach ($definition->languages as $lang)
                <span class="tag">{{ strtoupper($lang) }}</span>
            @endforeach
            @foreach ($definition->channels as $ch)
                <span class="tag">{{ $ch }}</span>
            @endforeach
        </p>
    </div>

    @unless ($hasProvider)
        <div class="note">
            <strong>{{ __("Aucune clé d'IA n'est configurée sur ce serveur.") }}</strong>
            {{ __("La démo ne peut pas répondre tant qu'une clé n'est pas ajoutée : aucune réponse ne sera inventée à la place.") }}
        </div>
    @endunless

    <h2>{{ __('Essayer') }}</h2>
    <form method="POST" action="{{ route('agents.demo', $agent->sector) }}">
        @csrf
        <input type="hidden" name="agent" value="{{ $agent->id }}">
        <label for="q">{{ __('Posez une question comme le ferait un client') }}</label>
        <input type="text" id="q" name="question" placeholder="{{ $questions[0] ?? '' }}" maxlength="500">
        <div class="row" style="margin-top:.9rem">
            <button type="submit" class="btn btn-primary">{{ __('Démo') }}</button>
        </div>
    </form>

    <h2>{{ __("Ce que l'agent sait") }}</h2>
    @if (empty($agent->knowledge))
        <p class="empty">{{ __("Aucune information enregistrée — l'agent dira qu'il ne sait pas.") }}</p>
    @else
        <table>
            @foreach ($agent->knowledge as $label => $value)
                <tr><td>{{ ucfirst($label) }}</td><td>{{ $value }}</td></tr>
            @endforeach
        </table>
    @endif

    <h2>{{ __('Sécurité') }}</h2>
    <table>
        <tr>
            <td>{{ __('Actions confirmées') }}</td>
            <td>{{ implode(', ', $definition->confirmation['always_confirm'] ?? []) ?: '—' }}</td>
        </tr>
        <tr>
            <td>{{ __('Réponses directes') }}</td>
            <td>{{ implode(', ', $definition->confirmation['never_confirm'] ?? []) ?: '—' }}</td>
        </tr>
        <tr>
            <td>{{ __('Passe à un humain en dessous de') }}</td>
            <td>{{ $definition->confirmation['handoff_below'] ?? '—' }}</td>
        </tr>
        <tr>
            <td>{{ __('Contact humain') }}</td>
            <td>{{ $agent->handoff_to ?: '—' }}</td>
        </tr>
    </table>

    <p class="back"><a href="{{ route('agents.index') }}">{{ __('Tous les agents') }}</a></p>

</x-agents::layouts.app>
