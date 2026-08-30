<x-agents::layouts.app :title="__('Agents')">

    <h1>{{ __('Choisissez un modèle') }}</h1>
    <p class="lead">{{ __("Essayez-le avec le bouton Démo, puis créez le vôtre.") }}</p>

    @unless ($hasProvider)
        <div class="note">
            {{ __("Aucune clé d'IA n'est configurée sur ce serveur : le bouton Démo affichera une erreur au lieu d'une vraie réponse.") }}
        </div>
    @endunless

    <div class="grid">
        @foreach ($templates as $t)
            <div class="card">
                <h3>{{ $t->label }}</h3>
                <p>{{ $t->description }}</p>
                <div class="row">
                    <a class="btn" href="{{ route('agents.demo', $t->sector) }}">{{ __('Démo') }}</a>
                    <a class="btn btn-primary" href="{{ route('agents.create', $t->sector) }}">{{ __('Créer') }}</a>
                </div>
            </div>
        @endforeach
    </div>

    <h2>{{ __('Vos agents') }}</h2>

    @forelse ($agents as $agent)
        <div class="card" style="margin-bottom:.7rem">
            <h3><a href="{{ route('agents.show', $agent) }}">{{ $agent->name }}</a></h3>
            <p style="margin-bottom:0">
                <span class="tag">{{ $agent->sector }}</span>
                <span class="tag">{{ count($agent->knowledge ?? []) }} {{ __('infos') }}</span>
            </p>
        </div>
    @empty
        <p class="empty">{{ __("Aucun agent pour l'instant.") }}</p>
    @endforelse

</x-agents::layouts.app>
