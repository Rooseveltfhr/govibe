<x-agents::layouts.app :title="__('Démo')">

    <h1>{{ __('Démo') }} — {{ $definition->name }}</h1>
    <p class="lead">
        {{ __("Ces réponses viennent du vrai modèle, avec le vrai routeur. Rien n'est simulé.") }}
    </p>

    @if ($error)
        <div class="note">{{ $error }}</div>
    @endif

    <form method="POST" action="{{ route('agents.demo', $sector) }}">
        @csrf
        @if ($agentModel)
            <input type="hidden" name="agent" value="{{ $agentModel->id }}">
        @endif
        <label for="q">{{ __('Poser une autre question') }}</label>
        <input type="text" id="q" name="question" value="{{ $asked }}" maxlength="500">
        <div class="row" style="margin-top:.9rem">
            <button type="submit" class="btn btn-primary">{{ __('Envoyer') }}</button>
            @if ($agentModel)
                <a class="btn" href="{{ route('agents.show', $agentModel) }}">{{ __("Retour à l'agent") }}</a>
            @else
                <a class="btn" href="{{ route('agents.create', $sector) }}">{{ __('Créer cet agent') }}</a>
            @endif
        </div>
    </form>

    @if ($turns)
        <h2>{{ __('Conversation') }}</h2>
        @foreach ($turns as $turn)
            <div class="turn">
                <p class="q">{{ $turn->question }}</p>
                <p class="a">{{ $turn->answer }}</p>
                <div class="meta">{{ $turn->provider }} · {{ $turn->model }} · {{ $turn->latencyMs }} ms</div>
            </div>
        @endforeach
    @elseif (! $error)
        <p class="empty">{{ __('Aucune réponse.') }}</p>
    @endif

    <p style="margin-top:1.5rem"><a href="{{ route('agents.index') }}">← {{ __('Tous les modèles') }}</a></p>

</x-agents::layouts.app>
