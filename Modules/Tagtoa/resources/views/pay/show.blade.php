{{-- TAGTOA Pay — page publique (standalone, mobile-first, vanilla JS, 3G).
     Variables : $page, $methods --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $page->title ?: __('Paiement') }} — TAGTOA</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--blk:#0A0A0A;--bg:#F5F5F3;--sf:#fff;--blue:#2cb809;--blue-deep:#239406;--blue-pale:rgba(44,184,9,.08);--green:#1D9E75;--red:#E0473E;--bd:rgba(0,0,0,.08);--fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif}
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:var(--fb);background:var(--bg);color:var(--blk);line-height:1.5}
        .wrap{max-width:480px;margin:0 auto;min-height:100vh;padding-bottom:90px}
        .hd{background:var(--blk);color:#fff;padding:30px 22px 26px;border-radius:0 0 26px 26px;position:relative;overflow:hidden}
        .hd::after{content:"";position:absolute;right:-40px;top:-40px;width:160px;height:160px;background:radial-gradient(circle,var(--blue) 0%,transparent 70%);opacity:.35}
        .badge{display:inline-flex;align-items:center;gap:6px;font:600 11px var(--fh);letter-spacing:.12em;text-transform:uppercase;background:var(--blue);padding:5px 11px;border-radius:999px}
        .hd h1{font-family:var(--fh);font-size:23px;margin:14px 0 4px;position:relative}
        .hd p{font-size:14px;opacity:.8;position:relative}
        .sec{font:600 13px var(--fh);letter-spacing:.05em;text-transform:uppercase;color:#777;margin:22px 22px 12px}
        .methods{padding:0 16px;display:flex;flex-direction:column;gap:10px}
        .m{background:var(--sf);border:1px solid var(--bd);border-radius:16px;padding:15px 16px;display:flex;align-items:center;gap:14px;cursor:pointer;width:100%;text-align:left;font:inherit;transition:all .25s cubic-bezier(.4,0,.2,1)}
        .m:active{transform:scale(.985)}.m.on{border-color:var(--blue);background:var(--blue-pale);box-shadow:0 4px 18px rgba(44,184,9,.12)}
        .m-ic{width:46px;height:46px;border-radius:12px;background:var(--blk);color:#fff;display:flex;align-items:center;justify-content:center;font-size:19px;flex-shrink:0}
        .m.on .m-ic{background:var(--blue)}
        .m-tx{flex:1;min-width:0}.m-tx b{display:block;font:600 15px var(--fh)}.m-tx span{font-size:12.5px;color:#888;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block}
        .m-ck{width:22px;height:22px;border-radius:50%;border:2px solid var(--bd);display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;flex-shrink:0}
        .m.on .m-ck{background:var(--blue);border-color:var(--blue)}.m:not(.on) .m-ck i{display:none}
        .det{display:none;padding:0 16px;margin-top:14px}.det.show{display:block}
        .card{background:var(--sf);border:1px solid var(--bd);border-radius:18px;padding:18px}
        .qr{text-align:center;margin-bottom:12px}.qr img{width:170px;height:170px;border-radius:12px;border:1px solid var(--bd)}
        .kv{display:flex;justify-content:space-between;gap:12px;padding:9px 0;border-bottom:1px dashed var(--bd);font-size:14px}.kv:last-of-type{border-bottom:0}
        .kv span{color:#888}.kv b{font-family:var(--fh);text-align:right;word-break:break-all}
        .copy{background:var(--blue-pale);color:var(--blue-deep);border:0;border-radius:8px;padding:5px 10px;font:600 12px var(--fh);cursor:pointer;margin-left:8px}
        .instr{background:var(--bg);border-radius:12px;padding:12px 14px;font-size:13.5px;color:#555;margin-top:12px}
        .lbl{font:600 12.5px var(--fh);color:#666;display:block;margin:12px 0 6px}
        .inp{width:100%;padding:13px 14px;border:1.5px solid var(--bd);border-radius:12px;font:15px var(--fb);background:#fff}.inp:focus{outline:0;border-color:var(--blue)}
        .file{display:flex;align-items:center;gap:12px;border:1.5px dashed var(--bd);border-radius:12px;padding:14px;cursor:pointer;color:#888;font-size:14px}
        .file.req{border-color:var(--blue);color:var(--blue-deep);background:var(--blue-pale)}.file input{display:none}
        .prev{margin-top:10px;border-radius:12px;max-height:200px;width:100%;object-fit:cover;display:none}
        .err{color:var(--red);font-size:12.5px;margin-top:5px;display:block}
        .ok{background:#eafaf3;border:1px solid var(--green);color:#0e5f44;border-radius:14px;padding:14px 16px;margin:16px;display:flex;gap:10px;align-items:center;font-size:14px}
        .info{background:#fff5e6;border:1px solid #E08A1E;color:#7a5200;border-radius:14px;padding:14px 16px;margin:16px;display:flex;gap:10px;align-items:center;font-size:14px}
        .auto-tag{display:inline-flex;align-items:center;gap:4px;font:700 9.5px var(--fh);letter-spacing:.04em;text-transform:uppercase;background:var(--blue);color:#fff;padding:2px 7px;border-radius:999px;margin-left:6px;vertical-align:middle}
        .brand{width:40px;height:40px;border-radius:11px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;flex-shrink:0;overflow:hidden}
        .brand img{width:100%;height:100%;object-fit:cover}
        .btn{border:0;border-radius:14px;padding:15px;font:600 15px var(--fh);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;width:100%}
        .btn-p{background:var(--blue);color:#fff}
        .bar{position:fixed;bottom:0;left:50%;transform:translateX(-50%);width:100%;max-width:480px;background:#fff;border-top:1px solid var(--bd);padding:12px 16px;display:flex;gap:10px}
        .bg{background:var(--blk);color:#fff;flex:0 0 54px;border:0;border-radius:14px;cursor:pointer}
        .foot{text-align:center;padding:22px;color:#aaa;font-size:12px}.foot b{font-family:var(--fh);color:#777}
        @media (prefers-reduced-motion:reduce){*{transition:none!important}}
    </style>
</head>
<body>
<div style="position:fixed;top:12px;right:12px;z-index:50">@include('tagtoa::partials.lang')</div>
<div class="wrap">
    <header class="hd">
        <span class="badge"><i class="fa-solid fa-wifi"></i> TAGTOA PAY</span>
        <h1>{{ $page->title ?: __('Effectuer un paiement') }}</h1>
        @if($page->description)<p>{{ $page->description }}</p>@endif
    </header>

    @if(session('proof_submitted'))
        <div class="ok"><i class="fa-solid fa-circle-check"></i><div>{{ __('Preuve reçue! Le bénéficiaire va vérifier.') }}</div></div>
    @endif
    @if(session('error'))
        <div class="info"><i class="fa-solid fa-circle-info"></i><div>{{ session('error') }}</div></div>
    @endif

    @if($methods->isEmpty())
        <div class="foot" style="padding:50px 22px">{{ __('Aucune méthode de paiement active.') }}</div>
    @else
        <p class="sec">{{ __('Choisissez une méthode') }}</p>
        <div class="methods" id="methods">
            @foreach($methods as $m)
                <button type="button" class="m" data-id="{{ $m->id }}" onclick="pick(this,{{ $m->id }})">
                    <span class="m-ic" style="background:{{ $m->brand_color }}">
                        @if($m->logo_url)<img src="{{ $m->logo_url }}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:12px">@else<i class="{{ $m->icon }}"></i>@endif
                    </span>
                    <span class="m-tx">
                        <b>{{ $m->display_label }}
                            @if($m->isAuto())<span class="auto-tag"><i class="fa-solid fa-bolt"></i> {{ __('Automatique') }}</span>@endif
                        </b>
                        <span>{{ $m->institution ?: ($m->account_number ?: ($m->account_holder ?: __('Voir détails'))) }}</span>
                    </span>
                    <span class="m-ck"><i class="fa-solid fa-check"></i></span>
                </button>
                <div class="det" id="det-{{ $m->id }}">
                    <div class="card">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
                            <span class="brand" style="background:{{ $m->brand_color }}">
                                @if($m->logo_url)<img src="{{ $m->logo_url }}" alt="">@else<i class="{{ $m->icon }}"></i>@endif
                            </span>
                            <b style="font-family:var(--fh);font-size:16px">{{ $m->display_label }}</b>
                        </div>
                        @if($m->onlineAvailable())
                            <a class="btn btn-p" href="{{ route('tagtoa.pay.checkout', [$page->alias, $m->id]) }}" style="margin-bottom:14px"><i class="fa-solid fa-bolt"></i> {{ __('Payer en ligne') }}</a>
                        @endif
                        @if($m->qr_url)<div class="qr"><img src="{{ $m->qr_url }}" alt="QR" loading="lazy"></div>@endif
                        @if($m->institution)<div class="kv"><span>{{ __('Institution') }}</span><b>{{ $m->institution }}</b></div>@endif
                        @if($m->account_holder)<div class="kv"><span>{{ __('Nom du compte') }}</span><b>{{ $m->account_holder }}</b></div>@endif
                        @if($m->account_number)<div class="kv"><span>{{ __('Numéro du compte') }}</span><b>{{ $m->account_number }}<button class="copy" type="button" data-copy="{{ $m->account_number }}" onclick="cp(this)">{{ __('Copier') }}</button></b></div>@endif
                        @if($m->instructions)<div class="instr"><i class="fa-solid fa-circle-info" style="color:var(--blue);margin-right:6px"></i>{{ $m->instructions }}</div>@endif

                        <form method="POST" action="{{ route('tagtoa.pay.submit-proof', $page->alias) }}" enctype="multipart/form-data" style="margin-top:14px">
                            @csrf
                            <input type="hidden" name="payment_method_id" value="{{ $m->id }}">
                            <label class="lbl">{{ __('Votre nom') }} *</label>
                            <input class="inp" name="payer_name" required maxlength="120" value="{{ old('payer_name') }}">
                            <label class="lbl">{{ __('Téléphone (WhatsApp)') }}</label>
                            <input class="inp" name="payer_phone" maxlength="40" value="{{ old('payer_phone') }}" placeholder="+509 ...">
                            <label class="lbl">{{ __('Montant') }} ({{ $page->default_currency }})</label>
                            <input class="inp" name="amount" type="number" step="0.01" min="0" value="{{ old('amount') }}" placeholder="0.00">
                            <label class="lbl">{{ __('Référence / N° transaction') }}</label>
                            <input class="inp" name="reference" maxlength="120" value="{{ old('reference') }}" placeholder="{{ __('Optionnel') }}">
                            <label class="lbl">{{ __('Preuve (capture)') }} {{ $m->requires_proof ? '*' : '' }}</label>
                            <label class="file {{ $m->requires_proof ? 'req' : '' }}">
                                <i class="fa-solid fa-cloud-arrow-up" style="font-size:20px"></i>
                                <span class="ftx">{{ __('Choisir une image…') }}</span>
                                <input type="file" name="proof" accept="image/*" {{ $m->requires_proof ? 'required' : '' }} onchange="pv(this)">
                            </label>
                            <img class="prev" alt="">
                            @error('proof')<span class="err">{{ $message }}</span>@enderror
                            <button class="btn btn-p" type="submit" style="margin-top:16px"><i class="fa-solid fa-paper-plane"></i> {{ __('Envoyer la preuve') }}</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="foot">{{ __('Sécurisé par') }} <b>TAGTOA</b> · tagtoa.com</div>
    <div class="bar">
        <button class="bg" onclick="sh()"><i class="fa-solid fa-share-nodes"></i></button>
        <button class="btn btn-p" onclick="document.getElementById('methods')?.scrollIntoView({behavior:'smooth'})"><i class="fa-solid fa-hand-point-up"></i> {{ __('Choisir & payer') }}</button>
    </div>
</div>
<script>
var cur=null;
function pick(btn,id){var d=document.getElementById('det-'+id);
    if(cur===id){btn.classList.remove('on');d.classList.remove('show');cur=null;return;}
    document.querySelectorAll('.m').forEach(function(b){b.classList.remove('on')});
    document.querySelectorAll('.det').forEach(function(x){x.classList.remove('show')});
    btn.classList.add('on');d.classList.add('show');cur=id;
    setTimeout(function(){d.scrollIntoView({behavior:'smooth',block:'center'})},120);}
function pv(i){var f=i.files[0];if(!f)return;i.parentNode.querySelector('.ftx').textContent=f.name;var img=i.closest('form').querySelector('.prev');var r=new FileReader();r.onload=function(e){img.src=e.target.result;img.style.display='block';};r.readAsDataURL(f);}
function cp(el){var t=el.getAttribute('data-copy');navigator.clipboard&&navigator.clipboard.writeText(t);var o=el.textContent;el.textContent='{{ __('Copié!') }}';setTimeout(function(){el.textContent=o;},1400);}
function sh(){if(navigator.share){navigator.share({title:document.title,url:location.href}).catch(function(){});}else{navigator.clipboard&&navigator.clipboard.writeText(location.href);alert('{{ __('Lien copié') }}');}}
@if($errors->any())var b=document.querySelector('.m');if(b)b.click();@endif
</script>
</body>
</html>
