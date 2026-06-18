{{-- ============================================================
     TAGTOA PAY — Page de paiement publique
     Standalone HTML · mobile-first · vanilla JS · optimisé 3G
     Variables : $page (TaGtoaPaymentPage), $methods (Collection)
     ============================================================ --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $page->title ?: __('Paiement') }} — TAGTOA PAY</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($page->description), 150) }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root{
            --tagtoa-black:#0A0A0A; --tagtoa-white:#FFF; --tagtoa-bg:#F5F5F3; --tagtoa-surface:#FFF;
            --tagtoa-blue:#0055FF; --tagtoa-blue-deep:#0040CC; --tagtoa-blue-pale:rgba(0,85,255,.08);
            --tagtoa-green:#1D9E75; --tagtoa-red:#E0473E; --tagtoa-border:rgba(0,0,0,.08);
            --fh:'Space Grotesk',sans-serif; --fb:'Nunito',-apple-system,sans-serif;
        }
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:var(--fb);background:var(--tagtoa-bg);color:var(--tagtoa-black);
            line-height:1.5;-webkit-font-smoothing:antialiased}
        .wrap{max-width:480px;margin:0 auto;min-height:100vh;background:var(--tagtoa-bg);
            padding-bottom:96px;position:relative}
        .hd{background:var(--tagtoa-black);color:#fff;padding:32px 22px 26px;
            border-radius:0 0 26px 26px;position:relative;overflow:hidden}
        .hd::after{content:"";position:absolute;right:-40px;top:-40px;width:160px;height:160px;
            background:radial-gradient(circle,var(--tagtoa-blue) 0%,transparent 70%);opacity:.35}
        .badge{display:inline-flex;align-items:center;gap:6px;font-family:var(--fh);font-weight:600;
            font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#fff;
            background:var(--tagtoa-blue);padding:5px 11px;border-radius:999px}
        .badge i{animation:pulse 1.6s infinite}
        @keyframes pulse{0%,100%{opacity:1}50%{opacity:.35}}
        .hd h1{font-family:var(--fh);font-size:23px;font-weight:700;margin:16px 0 6px;position:relative}
        .hd p{font-size:14px;opacity:.8;position:relative}
        .sec-t{font-family:var(--fh);font-weight:600;font-size:13px;letter-spacing:.06em;
            text-transform:uppercase;color:#777;margin:24px 22px 12px}
        .methods{padding:0 16px;display:flex;flex-direction:column;gap:10px}
        .m{background:var(--tagtoa-surface);border:1px solid var(--tagtoa-border);border-radius:16px;
            padding:15px 16px;display:flex;align-items:center;gap:14px;cursor:pointer;
            transition:all .25s cubic-bezier(.4,0,.2,1);width:100%;text-align:left;font:inherit}
        .m:active{transform:scale(.985)}
        .m.on{border-color:var(--tagtoa-blue);background:var(--tagtoa-blue-pale);
            box-shadow:0 4px 18px rgba(0,85,255,.12)}
        .m-ic{width:46px;height:46px;border-radius:12px;background:var(--tagtoa-black);color:#fff;
            display:flex;align-items:center;justify-content:center;font-size:19px;flex-shrink:0}
        .m.on .m-ic{background:var(--tagtoa-blue)}
        .m-tx{flex:1;min-width:0}
        .m-tx b{display:block;font-family:var(--fh);font-weight:600;font-size:15px}
        .m-tx span{font-size:12.5px;color:#888;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .m-ck{width:22px;height:22px;border-radius:50%;border:2px solid var(--tagtoa-border);flex-shrink:0;
            display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px}
        .m.on .m-ck{background:var(--tagtoa-blue);border-color:var(--tagtoa-blue)}
        .m:not(.on) .m-ck i{display:none}
        /* Détails méthode (révélés à la sélection) */
        .det{display:none;padding:0 16px;margin-top:14px}
        .det.show{display:block;animation:fade .3s cubic-bezier(.4,0,.2,1)}
        @keyframes fade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
        .card{background:var(--tagtoa-surface);border:1px solid var(--tagtoa-border);border-radius:18px;padding:18px}
        .qr{text-align:center;margin-bottom:14px}
        .qr img{width:180px;height:180px;border-radius:12px;border:1px solid var(--tagtoa-border)}
        .kv{display:flex;justify-content:space-between;gap:12px;padding:9px 0;border-bottom:1px dashed var(--tagtoa-border);font-size:14px}
        .kv:last-of-type{border-bottom:0}
        .kv span{color:#888}.kv b{font-family:var(--fh);text-align:right;word-break:break-all}
        .copy{background:var(--tagtoa-blue-pale);color:var(--tagtoa-blue-deep);border:0;border-radius:8px;
            padding:5px 10px;font:600 12px var(--fh);cursor:pointer;margin-left:8px}
        .instr{background:var(--tagtoa-bg);border-radius:12px;padding:12px 14px;font-size:13.5px;color:#555;margin-top:12px}
        .instr i{color:var(--tagtoa-blue);margin-right:6px}
        /* Formulaire preuve */
        .form{margin-top:16px}
        .lbl{font:600 12.5px var(--fh);color:#666;display:block;margin:12px 0 6px;letter-spacing:.02em}
        .inp{width:100%;padding:13px 14px;border:1.5px solid var(--tagtoa-border);border-radius:12px;
            font:15px var(--fb);background:#fff;transition:border-color .2s}
        .inp:focus{outline:0;border-color:var(--tagtoa-blue)}
        .file{display:flex;align-items:center;gap:12px;border:1.5px dashed var(--tagtoa-border);
            border-radius:12px;padding:14px;cursor:pointer;color:#888;font-size:14px}
        .file.req{border-color:var(--tagtoa-blue);color:var(--tagtoa-blue-deep);background:var(--tagtoa-blue-pale)}
        .file i{font-size:20px}
        .file input{display:none}
        .prev{margin-top:10px;border-radius:12px;max-height:200px;width:100%;object-fit:cover;display:none}
        .err{color:var(--tagtoa-red);font-size:12.5px;margin-top:5px;display:block}
        .ok{background:#eafaf3;border:1px solid var(--tagtoa-green);color:#0e5f44;border-radius:14px;
            padding:14px 16px;margin:16px;display:flex;gap:10px;align-items:center;font-size:14px}
        .ok i{color:var(--tagtoa-green);font-size:20px}
        .empty{text-align:center;color:#999;padding:40px 22px;font-size:14px}
        /* Bottom bar */
        .bar{position:fixed;bottom:0;left:50%;transform:translateX(-50%);width:100%;max-width:480px;
            background:#fff;border-top:1px solid var(--tagtoa-border);padding:12px 16px;
            display:flex;gap:10px;z-index:40}
        .btn{flex:1;border:0;border-radius:14px;padding:15px;font:600 15px var(--fh);cursor:pointer;
            display:flex;align-items:center;justify-content:center;gap:8px;transition:transform .15s}
        .btn:active{transform:scale(.97)}
        .btn-p{background:var(--tagtoa-blue);color:#fff}
        .btn-p:disabled{background:#c9d4e8;cursor:not-allowed}
        .btn-g{background:var(--tagtoa-black);color:#fff;flex:0 0 54px}
        .foot{text-align:center;padding:22px;color:#aaa;font-size:12px}
        .foot b{font-family:var(--fh);color:#777}
        @media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
    </style>
</head>
<body>
<div class="wrap">
    <header class="hd">
        <span class="badge"><i class="fa-solid fa-wifi"></i> TAGTOA PAY · NFC / QR</span>
        <h1>{{ $page->title ?: __('Effectuer un paiement') }}</h1>
        @if($page->description)<p>{{ $page->description }}</p>@endif
    </header>

    @if(session('proof_submitted'))
        <div class="ok">
            <i class="fa-solid fa-circle-check"></i>
            <div>{{ __('Preuve reçue! Le bénéficiaire va la vérifier et vous confirmer.') }}</div>
        </div>
    @endif

    @if($methods->isEmpty())
        <div class="empty">
            <i class="fa-regular fa-credit-card" style="font-size:32px;color:#ccc"></i>
            <p style="margin-top:12px">{{ __('Aucune méthode de paiement active pour le moment.') }}</p>
        </div>
    @else
        <p class="sec-t">{{ __('Choisissez une méthode') }}</p>
        <div class="methods" id="methods">
            @foreach($methods as $m)
                <button type="button" class="m" data-id="{{ $m->id }}"
                        data-requires-proof="{{ $m->requires_proof ? 1 : 0 }}">
                    <span class="m-ic"><i class="fa-solid {{ $m->icon }}"></i></span>
                    <span class="m-tx">
                        <b>{{ $m->display_label }}</b>
                        <span>{{ $m->account_number ?: ($m->account_holder ?: __('Voir détails')) }}</span>
                    </span>
                    <span class="m-ck"><i class="fa-solid fa-check"></i></span>
                </button>

                <div class="det" id="det-{{ $m->id }}">
                    <div class="card">
                        @if($m->qr_url)
                            <div class="qr"><img src="{{ $m->qr_url }}" alt="QR {{ $m->display_label }}" loading="lazy"></div>
                        @endif
                        @if($m->account_holder)
                            <div class="kv"><span>{{ __('Bénéficiaire') }}</span><b>{{ $m->account_holder }}</b></div>
                        @endif
                        @if($m->account_number)
                            <div class="kv">
                                <span>{{ __('Compte / N°') }}</span>
                                <b>{{ $m->account_number }}<button class="copy" type="button"
                                    onclick="tpCopy('{{ $m->account_number }}',this)">{{ __('Copier') }}</button></b>
                            </div>
                        @endif
                        @if($m->instructions)
                            <div class="instr"><i class="fa-solid fa-circle-info"></i>{{ $m->instructions }}</div>
                        @endif

                        <form class="form" method="POST"
                              action="{{ route('tagtoa.pay.submit-proof', $page->alias) }}"
                              enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="payment_method_id" value="{{ $m->id }}">

                            <label class="lbl">{{ __('Votre nom') }} *</label>
                            <input class="inp" name="payer_name" required maxlength="120"
                                   value="{{ old('payer_name') }}" placeholder="{{ __('Nom complet') }}">
                            @error('payer_name')<span class="err">{{ $message }}</span>@enderror

                            <label class="lbl">{{ __('Téléphone (WhatsApp)') }}</label>
                            <input class="inp" name="payer_phone" maxlength="40"
                                   value="{{ old('payer_phone') }}" placeholder="+509 ...">

                            <label class="lbl">{{ __('Montant') }} ({{ $page->default_currency }})</label>
                            <input class="inp" name="amount" type="number" step="0.01" min="0"
                                   value="{{ old('amount') }}" placeholder="0.00">

                            <label class="lbl">{{ __('Référence / N° de transaction') }}</label>
                            <input class="inp" name="reference" maxlength="120"
                                   value="{{ old('reference') }}" placeholder="{{ __('Optionnel') }}">

                            <label class="lbl">
                                {{ __('Preuve de paiement (capture)') }}
                                {{ $m->requires_proof ? '*' : '' }}
                            </label>
                            <label class="file {{ $m->requires_proof ? 'req' : '' }}">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <span class="file-tx">{{ __('Choisir une image…') }}</span>
                                <input type="file" name="proof" accept="image/*"
                                       {{ $m->requires_proof ? 'required' : '' }}
                                       onchange="tpPrev(this)">
                            </label>
                            <img class="prev" alt="">
                            @error('proof')<span class="err">{{ $message }}</span>@enderror

                            <button type="submit" class="btn btn-p" style="margin-top:18px;width:100%">
                                <i class="fa-solid fa-paper-plane"></i> {{ __('Envoyer la preuve') }}
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="foot">{{ __('Sécurisé par') }} <b>TAGTOA</b> · tagtoa.com</div>

    <div class="bar">
        <button class="btn btn-g" type="button" onclick="tpShare()" aria-label="{{ __('Partager') }}">
            <i class="fa-solid fa-share-nodes"></i>
        </button>
        <button class="btn btn-p" type="button" onclick="document.getElementById('methods')?.scrollIntoView({behavior:'smooth'})">
            <i class="fa-solid fa-hand-point-up"></i> {{ __('Choisir & payer') }}
        </button>
    </div>
</div>

<script>
(function(){
    var current=null;
    document.querySelectorAll('#methods .m').forEach(function(btn){
        btn.addEventListener('click',function(){
            var id=btn.dataset.id, det=document.getElementById('det-'+id);
            if(current===id){ // toggle off
                btn.classList.remove('on'); det.classList.remove('show'); current=null; return;
            }
            document.querySelectorAll('#methods .m').forEach(function(b){b.classList.remove('on')});
            document.querySelectorAll('.det').forEach(function(d){d.classList.remove('show')});
            btn.classList.add('on'); det.classList.add('show'); current=id;
            setTimeout(function(){det.scrollIntoView({behavior:'smooth',block:'center'})},120);
        });
    });
    @if($errors->any())
        var first=document.querySelector('.det'); if(first){
            var b=document.querySelector('#methods .m'); if(b) b.click();
        }
    @endif
})();
function tpPrev(input){
    var f=input.files[0], lbl=input.parentNode.querySelector('.file-tx'),
        img=input.closest('form').querySelector('.prev');
    if(!f){return;}
    lbl.textContent=f.name;
    var r=new FileReader(); r.onload=function(e){img.src=e.target.result;img.style.display='block';};
    r.readAsDataURL(f);
}
function tpCopy(txt,el){
    navigator.clipboard&&navigator.clipboard.writeText(txt);
    var o=el.textContent; el.textContent='{{ __('Copié!') }}'; setTimeout(function(){el.textContent=o;},1400);
}
function tpShare(){
    var d={title:document.title,url:location.href};
    if(navigator.share){navigator.share(d).catch(function(){});}
    else{tpCopy(location.href,{textContent:'',});alert('{{ __('Lien copié') }}: '+location.href);}
}
</script>
</body>
</html>
