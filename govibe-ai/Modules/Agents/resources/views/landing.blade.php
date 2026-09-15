@php
    // Tout lojik la isit la: yon fonksyon flèch andedan yon direktiv Blade
    // kraze parser la an pwodiksyon (CLAUDE.md §8).
    $heroVideo = file_exists(public_path('video/hero.mp4')) ? asset('video/hero.mp4') : null;

    $covers = [];
    foreach ($all as $t) {
        $jpg = 'img/covers/'.$t->sector.'.jpg';
        $covers[$t->sector] = file_exists(public_path($jpg)) ? asset($jpg) : null;
    }

    $integrations = [
        ['WhatsApp', __("L'agent répond sur le numéro de l'entreprise, jour et nuit.")],
        [__('Site web'), __('Une boîte de chat sur votre site, aux couleurs de votre marque.')],
        [__('Voix'), __("L'agent parle et écoute : le client appelle au lieu d'écrire.")],
    ];

    $supportLabels = [
        'thinking' => __('…'),
        'failed' => __("L'envoi a échoué. Réessayez."),
        'you' => __('Vous'),
        'bot' => __('Support LOUVIA'),
    ];
@endphp

<x-agents::layouts.app :title="__('LOUVIA')" :bare="true">

    <x-slot:head>
        <style>
            .band { padding: 0 1rem; }
            .band-inner { max-width: 1180px; margin: 0 auto; }

            /* ---- Antèt ---- */
            .hero { padding: 3rem 1rem 2rem; text-align: center; }
            .hero .display {
                font-family: var(--display);
                font-weight: 400;
                font-size: clamp(1.9rem, 5.6vw, 3.5rem);
                line-height: 1.04;
                letter-spacing: .005em;
                text-transform: uppercase;
                margin: 0 auto;
                max-width: 22ch;
                text-wrap: balance;
                color: var(--ink);
            }
            .hero .sub {
                margin: 1.1rem auto 0; max-width: 56ch;
                color: var(--ink-soft); font-size: 1.05rem;
            }
            .hero .cta { justify-content: center; margin-top: 1.5rem; }
            .hero .cta .btn { padding: .7rem 1.4rem; font-size: 1rem; }

            /* ---- Slide antèt: modèl ajan yo ---- */
            .covers { margin-top: 2rem; }
            .track {
                display: flex; gap: 1rem; overflow-x: auto; scroll-snap-type: x mandatory;
                scrollbar-width: none; padding-bottom: .25rem;
            }
            .track::-webkit-scrollbar { display: none; }
            .slide {
                flex: 0 0 100%; scroll-snap-align: center; position: relative;
                border-radius: 10px; overflow: hidden; border: 1px solid var(--line);
                min-height: 300px; display: flex; align-items: flex-end;
                background: #0d1a12;
            }
            .slide .cover, .slide video {
                position: absolute; inset: 0; width: 100%; height: 100%;
                object-fit: cover; border: 0;
            }
            /* Kouvèti ki desine lè pa gen foto: se pa yon twou gri, se yon
               fon ki asime tèt li. Foto a ranplase l depi li depoze. */
            .slide .cover.drawn {
                background:
                    radial-gradient(120% 90% at 12% 8%, rgba(255, 255, 255, .14), transparent 60%),
                    repeating-linear-gradient(115deg, rgba(255, 255, 255, .05) 0 2px, transparent 2px 22px),
                    linear-gradient(160deg, #0f8a3d 0%, #0b6c2f 48%, #0a2a19 100%);
            }
            .slide .glass {
                position: relative; width: 100%;
                padding: 1.4rem 1.4rem 1.5rem;
                background: linear-gradient(transparent, rgba(6, 18, 11, .88) 55%);
                color: #fff;
            }
            .slide .kicker {
                font-size: .72rem; letter-spacing: .12em; text-transform: uppercase;
                opacity: .85; margin: 0 0 .35rem;
            }
            .slide h3 {
                font-family: var(--display); font-weight: 400; text-transform: uppercase;
                font-size: clamp(1.5rem, 4.5vw, 2.2rem); line-height: 1.05; margin: 0 0 .4rem;
                color: #fff;
            }
            .slide p.line { margin: 0 0 1rem; font-size: .95rem; opacity: .92; max-width: 46ch; }
            .slide .btn { background: rgba(255, 255, 255, .1); border-color: rgba(255, 255, 255, .55); color: #fff; }
            .slide .btn:hover { background: rgba(255, 255, 255, .2); }
            .slide .btn-primary { background: var(--accent); border-color: var(--accent); color: #fff; }
            .slide .btn-primary:hover { background: #0d7a36; }

            .dots { display: flex; gap: .4rem; justify-content: center; margin-top: .9rem; }
            .dots button {
                width: 8px; height: 8px; padding: 0; border-radius: 50%; cursor: pointer;
                border: 1px solid var(--line-strong); background: var(--surface);
            }
            .dots button[aria-current="true"] { background: var(--accent); border-color: var(--accent); }

            /* ---- Seksyon ---- */
            /* Chapo a kole anlè: san sa a, yon lyen ankraj (#agents) rive
               ak tit la kache anba chapo a. */
            .section[id] { scroll-margin-top: calc(var(--topbar-h) + 12px); }
            .section { padding: 3rem 1rem 0; }
            .section-head { display: flex; align-items: flex-end; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.2rem; }
            .section-head h2 {
                font-family: var(--display); font-weight: 400; text-transform: uppercase;
                font-size: clamp(1.4rem, 4vw, 2rem); letter-spacing: .01em;
                margin: 0; color: var(--ink); text-transform: uppercase;
            }
            .section-head p { margin: 0; color: var(--muted); font-size: .95rem; max-width: 52ch; }
            .section-head .more { font-size: .9rem; }

            /* Kat modèl nan yon slide orizontal — bon sou telefòn (dwèt),
               bon sou òdinatè (yo tout parèt). */
            .rail { display: flex; gap: 1rem; overflow-x: auto; scroll-snap-type: x proximity; padding-bottom: .5rem; }
            .rail::-webkit-scrollbar { height: 6px; }
            .rail::-webkit-scrollbar-thumb { background: var(--line-strong); border-radius: 99px; }
            .mcard {
                flex: 0 0 min(85vw, 320px); scroll-snap-align: start;
                border: 1px solid var(--line); border-radius: 8px; background: var(--surface);
                padding: 1.1rem; display: flex; flex-direction: column;
            }
            .mcard .kind {
                font-size: .68rem; letter-spacing: .1em; text-transform: uppercase;
                color: var(--accent); font-weight: 700; margin-bottom: .45rem;
            }
            .mcard h3 { margin: 0 0 .3rem; font-size: 1.1rem; }
            .mcard .desc { margin: 0; color: var(--muted); font-size: .9rem; }
            .mcard ul { list-style: none; padding: 0; margin: .8rem 0 0; font-size: .88rem; color: var(--ink-soft); flex: 1; }
            .mcard ul li { padding: .16rem 0 .16rem .95rem; position: relative; }
            .mcard ul li::before {
                content: ""; position: absolute; left: 0; top: .72em;
                width: 5px; height: 1px; background: var(--line-strong);
            }
            .mcard .row { margin-top: 1.1rem; }

            /* ---- Lis konplè ---- */
            .rows { border: 1px solid var(--line); border-radius: 8px; overflow: hidden; }
            .rows .r {
                display: flex; gap: .75rem 1rem; align-items: center; flex-wrap: wrap;
                padding: .95rem 1.1rem; border-bottom: 1px solid var(--line); background: var(--surface);
            }
            .rows .r:last-child { border-bottom: 0; }
            .rows .r .name { font-weight: 650; }
            .rows .r .what { color: var(--muted); font-size: .9rem; flex: 1 1 260px; }
            .rows .r .row { margin-left: auto; }

            /* ---- Entegrasyon ---- */
            .tiles { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
            .tile { border: 1px solid var(--line); border-left: 3px solid var(--accent); border-radius: 8px; padding: 1rem 1.1rem; background: var(--surface); }
            .tile h3 { margin: 0 0 .3rem; font-size: 1rem; }
            .tile p { margin: 0; color: var(--muted); font-size: .92rem; }

            /* ---- Chat sipò ---- */
            .support-box {
                border: 1px solid var(--line); border-radius: 8px; background: var(--surface);
                max-width: 720px; margin: 0 auto; overflow: hidden;
            }
            .support-box .log { padding: .35rem 0; max-height: 340px; overflow-y: auto; }
            .support-box .foot { border-top: 1px solid var(--line); padding: .9rem; }
            .support-box .fields { display: flex; gap: .5rem; }
            .support-box input[type=text] { flex: 1; min-width: 0; }
            .support-hint { text-align: center; color: var(--muted); font-size: .86rem; margin: .8rem auto 0; max-width: 60ch; }

            /* ---- Pye paj ---- */
            .site-footer { margin-top: 3.5rem; border-top: 1px solid var(--line); padding: 2rem 1rem 3rem; }
            .site-footer .cols { display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
            .site-footer h4 { margin: 0 0 .5rem; font-size: .74rem; letter-spacing: .09em; text-transform: uppercase; color: var(--muted); }
            .site-footer a { display: block; padding: .15rem 0; font-size: .92rem; }
            .site-footer .legal { margin-top: 1.8rem; color: var(--muted); font-size: .84rem; }

            @media (min-width: 720px) {
                /* Sou ekran etwat lyen an desann anba tit la; sou laj li ale
                   sou bò dwat. Yon `margin-left:auto` toupatou t ap pouse l
                   deyò ekran an sou telefòn. */
                .section-head .more { margin-left: auto; }
            }
            @media (min-width: 860px) {
                .slide { min-height: 420px; }
                .mcard { flex-basis: 320px; }
            }
        </style>
    </x-slot:head>

    {{-- ─────────── Antèt: tèks anvan, apre sa modèl yo defile ─────────── --}}
    <section class="hero">
        <h1 class="display">Trouver vos employés moins cher pour votre entreprise.</h1>
        <p class="sub">
            {{ __("Des agents IA qui répondent à vos clients en créole et en français, sur WhatsApp, sur votre site et à la voix — sans jamais inventer un prix ni un horaire.") }}
        </p>
        <div class="row cta">
            <a class="btn btn-primary" href="{{ route('orders.create') }}">{{ __('Commander un agent') }}</a>
            <a class="btn" href="#agents">{{ __('Voir les modèles') }}</a>
        </div>
    </section>

    <section class="band covers" aria-label="{{ __('Modèles d’agents') }}">
        <div class="band-inner">
            <div class="track" id="cover-track">
                @foreach ($agents as $t)
                    <article class="slide">
                        @if ($loop->first && $heroVideo)
                            <video class="cover" src="{{ $heroVideo }}" autoplay muted loop playsinline></video>
                        @elseif ($covers[$t->sector])
                            <img class="cover" src="{{ $covers[$t->sector] }}" alt="{{ $t->label }}">
                        @else
                            <div class="cover drawn" aria-hidden="true"></div>
                        @endif

                        <div class="glass">
                            <p class="kicker">{{ __('Agent IA') }} · {{ $loop->iteration }}/{{ count($agents) }}</p>
                            <h3>{{ $t->label }}</h3>
                            <p class="line">{{ $t->description }}</p>
                            <div class="row">
                                <a class="btn btn-primary" href="{{ route('orders.create', $t->sector) }}">{{ __('Commander') }}</a>
                                <a class="btn" href="{{ route('agents.demo', $t->sector) }}">{{ __('Démo') }}</a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="dots" id="cover-dots"></div>
        </div>
    </section>

    {{-- ─────────── Blòk 1: chatbot ak asistan ─────────── --}}
    <section class="band section" id="chatbots">
        <div class="band-inner">
            <div class="section-head">
                <div>
                    <h2>{{ __('Chatbots et assistants IA') }}</h2>
                    <p>{{ __("Ils répondent. Aucun outil d'action, donc rien à confirmer : en ligne le jour même.") }}</p>
                </div>
                <a class="more" href="#catalogue">{{ __('Tous les modèles') }}</a>
            </div>

            <div class="rail">
                @foreach ($chatbots as $t)
                    <article class="mcard">
                        <span class="kind">{{ __('Chatbot') }}</span>
                        <h3>{{ $t->label }}</h3>
                        <p class="desc">{{ $t->description }}</p>
                        <ul>
                            @foreach ($t->capabilities as $capability)
                                <li>{{ $capability }}</li>
                            @endforeach
                        </ul>
                        <div class="row">
                            <a class="btn btn-primary" href="{{ route('agents.create', $t->sector) }}">{{ __('Créer avec ce modèle') }}</a>
                            <a class="btn" href="{{ route('agents.demo', $t->sector) }}">{{ __('Démo') }}</a>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ─────────── Blòk 2: ajan ki aji ─────────── --}}
    <section class="band section" id="agents">
        <div class="band-inner">
            <div class="section-head">
                <div>
                    <h2>{{ __('Agents IA') }}</h2>
                    <p>{{ __('Ils agissent : commandes, rendez-vous. Chaque action passe par une confirmation.') }}</p>
                </div>
                <a class="more" href="#catalogue">{{ __('Tous les modèles') }}</a>
            </div>

            <div class="rail">
                @foreach ($agents as $t)
                    <article class="mcard">
                        <span class="kind">{{ __('Agent') }}</span>
                        <h3>{{ $t->label }}</h3>
                        <p class="desc">{{ $t->description }}</p>
                        <ul>
                            @foreach ($t->capabilities as $capability)
                                <li>{{ $capability }}</li>
                            @endforeach
                        </ul>
                        <div class="row">
                            <a class="btn btn-primary" href="{{ route('agents.create', $t->sector) }}">{{ __('Créer avec ce modèle') }}</a>
                            <a class="btn" href="{{ route('agents.demo', $t->sector) }}">{{ __('Démo') }}</a>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ─────────── Lis konplè ─────────── --}}
    <section class="band section" id="catalogue">
        <div class="band-inner">
            <div class="section-head">
                <div>
                    <h2>{{ __('Tous les modèles') }}</h2>
                    <p>{{ __("Créez-le vous-même, ou commandez : un expert GOVIBE le monte pour vous.") }}</p>
                </div>
            </div>

            <div class="rows">
                @foreach ($all as $t)
                    <div class="r">
                        <span class="name">{{ $t->label }}</span>
                        <span class="tag">{{ $t->isChatbot() ? __('Chatbot') : __('Agent') }}</span>
                        <span class="what">{{ $t->description }}</span>
                        <span class="row">
                            <a class="btn btn-primary" href="{{ route('orders.create', $t->sector) }}">{{ __('Commander') }}</a>
                            <a class="btn" href="{{ route('agents.create', $t->sector) }}">{{ __('Créer') }}</a>
                            <a class="btn" href="{{ route('agents.demo', $t->sector) }}">{{ __('Démo') }}</a>
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ─────────── Entegrasyon ─────────── --}}
    <section class="band section" id="integrations">
        <div class="band-inner">
            <div class="section-head">
                <div>
                    <h2>{{ __('Intégrations') }}</h2>
                    <p>{{ __("Le même agent, plusieurs portes d'entrée : il garde la même mémoire et les mêmes règles.") }}</p>
                </div>
            </div>

            <div class="tiles">
                @foreach ($integrations as $integration)
                    <div class="tile">
                        <h3>{{ $integration[0] }}</h3>
                        <p>{{ $integration[1] }}</p>
                    </div>
                @endforeach
            </div>

            @unless ($hasVoice)
                <p class="support-hint">
                    {{ __("La voix est branchée sur ElevenLabs et s'active avec une clé : sans elle, l'agent répond par écrit.") }}
                </p>
            @endunless
        </div>
    </section>

    {{-- ─────────── Chat sipò, anvan pye paj la ─────────── --}}
    <section class="band section" id="support">
        <div class="band-inner">
            <div class="section-head">
                <div>
                    <h2>{{ __('Une question ? Écrivez-nous') }}</h2>
                    <p>{{ __("C'est notre propre chatbot, bâti sur le modèle que nous vendons. Ce qu'il fait ici, le vôtre le fera chez vous.") }}</p>
                </div>
            </div>

            <div class="support-box">
                <div class="log thread" id="support-log" hidden></div>
                <div class="foot">
                    <form id="support-form">
                        @csrf
                        <div class="fields">
                            <input type="text" id="support-q" name="question" maxlength="500"
                                   placeholder="{{ __('Posez votre question') }}" autocomplete="off">
                            <button type="submit" class="btn btn-primary">{{ __('Envoyer') }}</button>
                        </div>
                        <p class="status" id="support-status" role="status"></p>
                    </form>
                </div>
            </div>

            @unless ($hasProvider)
                <p class="support-hint">
                    {{ __("Aucune clé d'IA n'est configurée sur ce serveur : ce chat renverra une erreur au lieu d'une réponse inventée.") }}
                </p>
            @endunless
        </div>
    </section>

    <footer class="site-footer">
        <div class="band-inner">
            <div class="cols">
                <div>
                    <h4>{{ __('Modèles') }}</h4>
                    @foreach ($all as $t)
                        <a href="{{ route('agents.demo', $t->sector) }}">{{ $t->label }}</a>
                    @endforeach
                </div>
                <div>
                    <h4>{{ __('Commencer') }}</h4>
                    <a href="{{ route('orders.create') }}">{{ __('Commander un agent') }}</a>
                    <a href="{{ route('agents.index') }}">{{ __('Vos agents') }}</a>
                    <a href="#support">{{ __('Support') }}</a>
                </div>
                <div>
                    <h4>{{ __('Langue') }}</h4>
                    <a href="?lang=ht">Kreyòl</a>
                    <a href="?lang=fr">Français</a>
                    <a href="?lang=en">English</a>
                    <a href="?lang=es">Español</a>
                </div>
                <div>
                    <h4>{{ __('Contact') }}</h4>
                    <a href="https://wa.me/50933988754">WhatsApp +509 3398 8754</a>
                    <span class="empty">GOVIBE Innovation Hub</span>
                </div>
            </div>
            <p class="legal">© {{ date('Y') }} GOVIBE — LOUVIA</p>
        </div>
    </footer>

    <script>
        (function () {
            // ── Slide antèt la. San JS li rete yon lis ou ka glise ak dwèt ou:
            // JS la sèlman ajoute pwen yo ak avansman otomatik la.
            var track = document.getElementById('cover-track');
            var dots = document.getElementById('cover-dots');

            if (track && dots && track.children.length > 1) {
                var slides = Array.prototype.slice.call(track.children);

                slides.forEach(function (slide, i) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.setAttribute('aria-label', String(i + 1));
                    b.setAttribute('aria-current', i === 0 ? 'true' : 'false');
                    b.addEventListener('click', function () { go(i); });
                    dots.appendChild(b);
                });

                var current = 0;

                function mark(i) {
                    current = i;
                    for (var d = 0; d < dots.children.length; d++) {
                        dots.children[d].setAttribute('aria-current', d === i ? 'true' : 'false');
                    }
                }

                function go(i) {
                    mark(i);
                    track.scrollTo({left: slides[i].offsetLeft - track.offsetLeft, behavior: 'smooth'});
                }

                track.addEventListener('scroll', function () {
                    var nearest = Math.round(track.scrollLeft / track.clientWidth);
                    if (nearest !== current && slides[nearest]) { mark(nearest); }
                });

                var calm = window.matchMedia('(prefers-reduced-motion: reduce)');
                if (!calm.matches) {
                    var timer = setInterval(function () {
                        go((current + 1) % slides.length);
                    }, 6000);
                    track.addEventListener('pointerdown', function () { clearInterval(timer); });
                }
            }

            // ── Chat sipò.
            var form = document.getElementById('support-form');
            var input = document.getElementById('support-q');
            var log = document.getElementById('support-log');
            var status = document.getElementById('support-status');
            var labels = @json($supportLabels);
            var url = @json(route('support.ask'));

            if (!form) { return; }

            function bubble(who, text, mine) {
                var box = document.createElement('div');
                box.className = 'msg' + (mine ? ' from-user' : '');
                var head = document.createElement('p');
                head.className = 'who';
                head.textContent = who;
                var body = document.createElement('p');
                body.className = 'body';
                body.textContent = text;
                box.appendChild(head);
                box.appendChild(body);
                log.appendChild(box);
                log.hidden = false;
                log.scrollTop = log.scrollHeight;
            }

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var question = input.value.trim();
                if (!question) { return; }

                bubble(labels.you, question, true);
                input.value = '';
                status.textContent = labels.thinking;

                var data = new FormData();
                data.append('question', question);
                data.append('_token', form.querySelector('input[name="_token"]').value);

                fetch(url, {method: 'POST', body: data, headers: {'Accept': 'application/json'}})
                    .then(function (r) { return r.json().then(function (j) { return {ok: r.ok, body: j}; }); })
                    .then(function (res) {
                        status.textContent = '';
                        if (!res.ok) { bubble(labels.bot, res.body.error || labels.failed, false); return; }
                        bubble(labels.bot, res.body.reply, false);
                    })
                    .catch(function () { status.textContent = labels.failed; });
            });
        })();
    </script>

</x-agents::layouts.app>
