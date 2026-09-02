<x-agents::layouts.app :title="__('Démo')">

    <h1>{{ __('Démo') }} — {{ $definition->name }}</h1>
    <p class="lead">
        {{ __("L'agent se souvient de l'échange : commandez en plusieurs messages, comme un vrai client.") }}
    </p>

    @unless ($hasProvider)
        <div class="note">
            {{ __("Aucune clé d'IA n'est configurée sur ce serveur : le bouton Démo affichera une erreur au lieu d'une vraie réponse.") }}
        </div>
    @endunless

    @if ($error)
        <div class="note">{{ $error }}</div>
    @endif

    @if ($conversation->isEmpty())
        <h2>{{ __('Essayez par exemple') }}</h2>
        @foreach ($suggestions as $suggestion)
            <form method="POST" action="{{ route('agents.demo', $sector) }}" style="margin-bottom:.5rem">
                @csrf
                @if ($agentModel)
                    <input type="hidden" name="agent" value="{{ $agentModel->id }}">
                @endif
                <input type="hidden" name="question" value="{{ $suggestion }}">
                <button type="submit" class="btn">{{ $suggestion }}</button>
            </form>
        @endforeach
    @else
        <h2>{{ __('Conversation') }}</h2>
        @foreach ($conversation->turns as $turn)
            <div class="turn">
                @if ($turn->role === 'user')
                    <p class="q">{{ $turn->content }}</p>
                @else
                    <p class="a">{{ $turn->content }}</p>
                    @if (($turn->meta['provider'] ?? null))
                        <div class="meta">
                            {{ $turn->meta['provider'] }}
                            @if ($turn->meta['model'] ?? null) · {{ $turn->meta['model'] }} @endif
                            @if (isset($turn->meta['latency_ms'])) · {{ $turn->meta['latency_ms'] }} ms @endif
                        </div>
                    @endif
                @endif
            </div>
        @endforeach
    @endif

    <form method="POST" action="{{ route('agents.demo', $sector) }}">
        @csrf
        @if ($agentModel)
            <input type="hidden" name="agent" value="{{ $agentModel->id }}">
        @endif
        <label for="q">{{ __('Votre message') }}</label>
        <input type="text" id="q" name="question" maxlength="500" autofocus
               placeholder="{{ $suggestions[0] ?? '' }}">
        <div class="row" style="margin-top:.9rem">
            <button type="submit" class="btn btn-primary">{{ __('Envoyer') }}</button>
            @unless ($conversation->isEmpty())
                <button type="submit" name="reset" value="1" class="btn">{{ __('Recommencer') }}</button>
            @endunless
            @if ($agentModel)
                <a class="btn" href="{{ route('agents.show', $agentModel) }}">{{ __("Retour à l'agent") }}</a>
            @else
                <a class="btn" href="{{ route('agents.create', $sector) }}">{{ __('Créer cet agent') }}</a>
            @endif
        </div>
    </form>

    <p style="margin-top:1.5rem"><a href="{{ route('agents.index') }}">← {{ __('Tous les modèles') }}</a></p>

</x-agents::layouts.app>
