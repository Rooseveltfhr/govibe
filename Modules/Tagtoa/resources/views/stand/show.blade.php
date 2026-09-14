{{-- TAGTOA SMART STAND — la page qu'un client voit en scannant.

     Elle ne doit JAMAIS ressembler à une panne. Un stand est posé sur une
     table, devant quelqu'un qui a faim. Une erreur 404 ou une page de paiement
     lui feraient reposer son téléphone — et c'est le commerçant qui en paierait
     le prix, pas nous.

     Page autonome : ni barre latérale, ni tableau de bord. Le visiteur n'est
     pas un marchand connecté. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $standId }} — TAGTOA</title>
    <link rel="stylesheet" href="{{ route('tagtoa.asset', 'tagtoa-fonts.css') }}">
    <style>
        :root{--blk:#0A0A0A;--green:#2cb809;--muted:#6B7280;--bd:rgba(0,0,0,.09);
              --bg:#F7F8F6;--card:#fff;--red:#C0392B;
              --fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif}
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:var(--fb);background:var(--bg);color:var(--blk);
             min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
        .card{background:var(--card);border:1px solid var(--bd);border-radius:18px;
              padding:32px 26px;max-width:420px;width:100%;text-align:center;
              box-shadow:0 10px 40px rgba(0,0,0,.05)}
        .mark{font:700 26px/1 var(--fh);letter-spacing:-.02em}
        .mark span{color:var(--green)}
        .sid{font:600 13px/1 ui-monospace,monospace;color:var(--muted);
             letter-spacing:.1em;margin-top:10px}
        h1{font:700 21px/1.25 var(--fh);margin:22px 0 8px}
        p{color:var(--muted);font-size:15px;line-height:1.6}
        form{margin-top:22px;text-align:left}
        label{display:block;font-size:13px;font-weight:700;margin-bottom:7px}
        input{width:100%;padding:15px;border:1.5px solid var(--bd);border-radius:12px;
              font:600 20px/1 ui-monospace,monospace;letter-spacing:.18em;text-align:center;
              text-transform:uppercase;background:#fff;color:var(--blk)}
        input:focus{outline:2px solid var(--green);outline-offset:1px;border-color:transparent}
        .hint{font-size:12.5px;color:var(--muted);margin-top:8px;text-align:center}
        button{width:100%;margin-top:16px;padding:16px;border:0;border-radius:12px;
               background:var(--green);color:#fff;font:700 16px var(--fh);cursor:pointer}
        .err{background:rgba(192,57,43,.08);border-left:3px solid var(--red);color:var(--red);
             padding:11px 13px;border-radius:8px;font-size:14px;margin-top:16px;text-align:left}
        .foot{margin-top:24px;font-size:12.5px;color:var(--muted)}
        .foot a{color:var(--green);text-decoration:none;font-weight:700}
    </style>
</head>
<body>
<div class="card">
    <div class="mark">TAG<span>TOA</span></div>
    <div class="sid">{{ $standId }}</div>

    @if($go === 'activate')
        <h1>{{ __('Ce stand n\'est pas encore activé') }}</h1>
        <p>{{ __('Si vous venez de l\'acheter, grattez le film au dos du socle et tapez le code qui apparaît.') }}</p>

        <form method="POST" action="{{ route('tagtoa.stand.verify', $standId) }}">
            @csrf
            <label for="code">{{ __('Code d\'activation') }}</label>
            {{-- autocomplete off : ce code ne sert qu'une fois, le proposer
                 plus tard n'aiderait personne. --}}
            <input id="code" name="code" maxlength="12" required autofocus
                   autocomplete="off" autocapitalize="characters" spellcheck="false"
                   inputmode="text" placeholder="A3F9-K2MP">
            <p class="hint">{{ __('Huit caractères, sous le film gratté.') }}</p>

            @error('code')<div class="err">{{ $message }}</div>@enderror

            <button type="submit">{{ __('Activer ce stand') }}</button>
        </form>

        <p class="foot">
            {{ __('Vous n\'avez pas acheté ce stand ?') }}
            {{ __('Il appartient à un commerce qui ne l\'a pas encore configuré.') }}
        </p>

    @elseif($go === 'paused')
        {{-- Le client n'a pas à savoir qu'un abonnement a expiré, ni à voir une
             page de paiement. On prévient le commerçant, pas son client. --}}
        <h1>{{ __('Ce menu n\'est pas disponible') }}</h1>
        <p>{{ __('Adressez-vous au commerce — il pourra vous renseigner directement.') }}</p>

    @elseif($go === 'closed')
        <h1>{{ __('Ce stand n\'est plus en service') }}</h1>
        <p>{{ __('Il a été remplacé ou retiré. Si vous l\'avez trouvé, merci de le signaler au commerce.') }}</p>

    @else
        {{-- Identifiant inconnu ou mal formé : MÊME message dans les deux cas.
             Un identifiant absent ne doit pas se distinguer d'un identifiant
             mal formé, sinon on apprend à l'attaquant à quoi ressemble un
             identifiant valide. --}}
        <h1>{{ __('Ce numéro ne correspond à aucun stand') }}</h1>
        <p>{{ __('Vérifiez ce qui est imprimé sur le socle. Le numéro ressemble à TG-000001.') }}</p>
    @endif

    <p class="foot"><a href="https://tagtoa.com">tagtoa.com</a></p>
</div>
</body>
</html>
