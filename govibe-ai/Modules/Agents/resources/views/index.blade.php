<x-agents::layouts.app :title="__('Modèles')">

    <div class="page-head">
        <h1>{{ __('Choisissez un modèle') }}</h1>
        <p class="lead">
            {{ __("Chaque modèle est un agent déjà réglé pour un métier. Essayez-le avec Démo, puis créez le vôtre en remplissant ce que votre entreprise sait.") }}
        </p>
    </div>

    @unless ($hasProvider)
        <div class="note">
            <strong>{{ __("Aucune clé d'IA n'est configurée sur ce serveur.") }}</strong>
            {{ __("Le catalogue et la création fonctionnent ; le bouton Démo affichera une erreur au lieu d'une vraie réponse tant qu'une clé n'est pas ajoutée.") }}
        </div>
    @endunless

    <div class="grid">
        @foreach ($templates as $t)
            <div class="card">
                <div class="card-body">
                    <h3>{{ $t->label }}</h3>
                    <p class="desc">{{ $t->description }}</p>
                    @if ($t->capabilities !== [])
                        <ul class="does">
                            @foreach ($t->capabilities as $capability)
                                <li>{{ $capability }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <div class="row">
                    <a class="btn btn-primary" href="{{ route('agents.create', $t->sector) }}">{{ __('Créer') }}</a>
                    <a class="btn" href="{{ route('agents.demo', $t->sector) }}">{{ __('Démo') }}</a>
                </div>
            </div>
        @endforeach
    </div>

    <h2>{{ __('Vos agents') }}</h2>

    @if ($agents->isEmpty())
        <p class="empty">{{ __("Aucun agent pour l'instant. Créez-en un à partir d'un modèle ci-dessus.") }}</p>
    @else
        <div class="list">
            @foreach ($agents as $agent)
                <div class="item">
                    <span class="name"><a href="{{ route('agents.show', $agent) }}">{{ $agent->name }}</a></span>
                    <span class="tag">{{ $agent->sector }}</span>
                    <span class="empty">{{ count($agent->knowledge ?? []) }} {{ __('infos') }}</span>
                    <span class="right">
                        <a class="btn" href="{{ route('agents.show', $agent) }}">{{ __('Ouvrir') }}</a>
                    </span>
                </div>
            @endforeach
        </div>
    @endif

</x-agents::layouts.app>
