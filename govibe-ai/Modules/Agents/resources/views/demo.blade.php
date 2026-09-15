@php
    // Blade pa tolere fonksyon flèch nan yon direktiv (@json, @if…) — tout
    // lojik la ap viv isit la, epi vi a jwenn varyab senp.
    $agentId = $agentModel ? $agentModel->id : null;
    $isCall = $mode === 'call';
    $voiceUrl = route('agents.demo.voice', $sector);
    $chatUrl = route('agents.demo', $sector);
    $modeCallUrl = route('agents.demo', array_filter(['sector' => $sector, 'agent' => $agentId, 'mode' => 'call']));
    $modeChatUrl = route('agents.demo', array_filter(['sector' => $sector, 'agent' => $agentId, 'mode' => 'chat']));

    // Tout tèks JS la konstwi ISIT LA. Yon tablo ki gen `=>` andedan yon
    // direktiv @json(...) kraze parser Blade la (« Unclosed '[' ») epi paj la
    // tonbe an pwodiksyon — se erè PR #41 nan TAGTOA a.
    $labels = [
        'listening' => __('Le micro est ouvert — parlez, puis fermez le micro.'),
        'closing' => __('Micro fermé. Envoi…'),
        'thinking' => __("L'agent réfléchit…"),
        'speaking' => __("L'agent répond."),
        'denied' => __('Le micro est refusé par le navigateur. Écrivez votre message.'),
        'failed' => __("L'envoi a échoué. Réessayez."),
        'speak' => __('Parler'),
        'close' => __('Fermer le micro'),
        'client' => __('Client'),
    ];
@endphp

<x-agents::layouts.app :title="__('Démo')">

    <div class="stage">

        <h1>{{ $definition->name }}</h1>
        <p class="lead">
            {{ __("Appelez l'agent et il vous répond à la voix ; écrivez-lui et il répond par écrit. C'est le même agent, avec la même mémoire.") }}
        </p>

        <div class="modes">
            <a class="mode-btn" href="{{ $modeCallUrl }}" aria-current="{{ $isCall ? 'true' : 'false' }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.24 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1 11.4 11.4 0 0 0 .57 3.6 1 1 0 0 1-.25 1z"/></svg>
                {{ __('Appel') }}
            </a>
            <a class="mode-btn" href="{{ $modeChatUrl }}" aria-current="{{ $isCall ? 'false' : 'true' }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H9l-5 4V6a2 2 0 0 1 2-2z"/></svg>
                {{ __('Chat') }}
            </a>
        </div>

        @unless ($hasProvider)
            <div class="note">
                <strong>{{ __("Aucune clé d'IA n'est configurée sur ce serveur.") }}</strong>
                {{ __("La démo ne peut pas répondre tant qu'une clé n'est pas ajoutée : aucune réponse ne sera inventée à la place.") }}
            </div>
        @endunless

        @if ($isCall && ! $hasVoice)
            <div class="note">
                {{ __("La voix n'est pas encore activée sur ce serveur : l'agent répondra par écrit. Ajoutez une clé ElevenLabs pour l'entendre.") }}
            </div>
        @endif

        @if ($error)
            <div class="note">{{ $error }}</div>
        @endif

        @if ($conversation->isEmpty())
            <h2>{{ __('Essayez par exemple') }}</h2>
            <div class="row" style="justify-content:center">
                @foreach ($suggestions as $suggestion)
                    <form method="POST" action="{{ $chatUrl }}">
                        @csrf
                        @if ($agentId)
                            <input type="hidden" name="agent" value="{{ $agentId }}">
                        @endif
                        <input type="hidden" name="mode" value="{{ $mode }}">
                        <input type="hidden" name="question" value="{{ $suggestion }}">
                        <button type="submit" class="btn">{{ $suggestion }}</button>
                    </form>
                @endforeach
            </div>
        @endif

        <div class="thread" id="thread" @if ($conversation->isEmpty()) hidden @endif>
            @foreach ($conversation->turns as $turn)
                <div class="msg {{ $turn->role === 'user' ? 'from-user' : '' }}">
                    <p class="who">{{ $turn->role === 'user' ? __('Client') : $definition->name }}</p>
                    <p class="body">{{ $turn->content }}</p>
                    @if ($turn->role !== 'user' && ($turn->meta['provider'] ?? null))
                        <div class="meta">
                            {{ $turn->meta['provider'] }}
                            @if ($turn->meta['model'] ?? null) · {{ $turn->meta['model'] }} @endif
                            @if (isset($turn->meta['latency_ms'])) · {{ $turn->meta['latency_ms'] }} ms @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <form method="POST" action="{{ $chatUrl }}" class="composer" id="composer">
            @csrf
            @if ($agentId)
                <input type="hidden" name="agent" value="{{ $agentId }}">
            @endif
            <input type="hidden" name="mode" value="{{ $mode }}">

            <div class="fields">
                <button type="button" class="mic" id="mic" data-state="idle"
                        title="{{ __('Parler') }}" aria-label="{{ __('Parler') }}" hidden>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 11h2a5 5 0 0 0 10 0h2a7 7 0 0 1-6 6.93V21h3v2H8v-2h3v-3.07A7 7 0 0 1 5 11z"/></svg>
                </button>
                <input type="text" id="q" name="question" maxlength="500"
                       placeholder="{{ __('Écrivez, ou parlez avec le micro') }}" autocomplete="off">
                <button type="submit" class="btn btn-primary" id="send">{{ __('Envoyer') }}</button>
            </div>

            <p class="status" id="status" role="status"></p>

            <div class="row" style="justify-content:center">
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

        <audio id="reply-audio" hidden></audio>

        <p class="back"><a href="{{ route('agents.index') }}">{{ __('Tous les modèles') }}</a></p>
    </div>

    <script>
        // Mòd chat la mache SAN JavaScript (fòm nan pote l). JS la ajoute de
        // bagay: mikwo a, epi nan mòd apèl, yon repons ki pale san rechaje paj.
        (function () {
            var mic = document.getElementById('mic');
            var composer = document.getElementById('composer');
            var input = document.getElementById('q');
            var send = document.getElementById('send');
            var status = document.getElementById('status');
            var thread = document.getElementById('thread');
            var player = document.getElementById('reply-audio');

            var url = @json($voiceUrl);
            var agentId = @json($agentId);
            var isCall = @json($isCall);
            var token = document.querySelector('input[name="_token"]').value;

            var labels = @json($labels);

            var canRecord = navigator.mediaDevices && window.MediaRecorder;

            if (canRecord) {
                mic.hidden = false;
            }

            function say(text) {
                status.textContent = text || '';
            }

            function bubble(role, text, who) {
                var box = document.createElement('div');
                box.className = 'msg' + (role === 'user' ? ' from-user' : '');
                var head = document.createElement('p');
                head.className = 'who';
                head.textContent = who;
                var body = document.createElement('p');
                body.className = 'body';
                body.textContent = text;
                box.appendChild(head);
                box.appendChild(body);
                thread.appendChild(box);
                thread.hidden = false;
                box.scrollIntoView({block: 'nearest'});
            }

            function post(data) {
                data.append('_token', token);
                if (agentId) { data.append('agent', agentId); }
                if (isCall) { data.append('speak', '1'); }

                say(labels.thinking);
                send.disabled = true;

                return fetch(url, {method: 'POST', body: data, headers: {'Accept': 'application/json'}})
                    .then(function (r) { return r.json().then(function (j) { return {ok: r.ok, body: j}; }); })
                    .then(function (res) {
                        send.disabled = false;

                        if (!res.ok) {
                            say(res.body.error || labels.failed);
                            return;
                        }

                        if (res.body.transcript) {
                            bubble('user', res.body.transcript, labels.client);
                        }
                        bubble('assistant', res.body.reply, document.querySelector('.stage h1').textContent);

                        if (res.body.audio) {
                            player.src = res.body.audio;
                            player.play();
                            say(labels.speaking);
                        } else {
                            say('');
                        }
                    })
                    .catch(function () {
                        send.disabled = false;
                        say(labels.failed);
                    });
            }

            // ── Mikwo: yon sèl bouton, de eta. Louvri, epi fèmen pou voye.
            var recorder = null;
            var chunks = [];

            mic.addEventListener('click', function () {
                if (recorder && recorder.state === 'recording') {
                    say(labels.closing);
                    recorder.stop();
                    return;
                }

                navigator.mediaDevices.getUserMedia({audio: true}).then(function (stream) {
                    recorder = new MediaRecorder(stream);
                    chunks = [];

                    recorder.ondataavailable = function (e) { chunks.push(e.data); };

                    recorder.onstop = function () {
                        stream.getTracks().forEach(function (t) { t.stop(); });
                        mic.dataset.state = 'idle';
                        mic.title = labels.speak;

                        var blob = new Blob(chunks, {type: recorder.mimeType || 'audio/webm'});
                        var data = new FormData();
                        data.append('audio', blob, 'vwa.webm');
                        post(data);
                    };

                    recorder.start();
                    mic.dataset.state = 'recording';
                    mic.title = labels.close;
                    say(labels.listening);
                }).catch(function () {
                    say(labels.denied);
                });
            });

            // Nan mòd apèl, yon mesaj ekri dwe reponn ak vwa tou — kidonk li
            // pase pa menm chemen an olye li rechaje paj la.
            composer.addEventListener('submit', function (e) {
                if (!isCall || !input.value.trim() || e.submitter && e.submitter.name === 'reset') {
                    return;
                }

                e.preventDefault();
                var data = new FormData();
                data.append('text', input.value.trim());
                bubble('user', input.value.trim(), labels.client);
                input.value = '';
                post(data);
            });
        })();
    </script>

</x-agents::layouts.app>
