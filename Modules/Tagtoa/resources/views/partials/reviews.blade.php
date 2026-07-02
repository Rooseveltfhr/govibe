{{-- TAGTOA REVIEWS — section publique réutilisable.
     Variables attendues : $subjectType, $subjectId, $subjectAlias (nullable),
     $reviews (collection d'avis approuvés), $summary (count/average/distribution).
     Utilise les variables CSS de la page hôte avec repli (var(--acc,#2cb809)…). --}}
@php
    $rvAvg = $summary['average'] ?? 0;
    $rvCount = $summary['count'] ?? 0;
@endphp
<section class="sec tg-reviews" id="reviews" style="padding:22px 16px 4px">
    <style>
        .tg-reviews .rv-head{display:flex;align-items:center;gap:14px;margin-bottom:14px}
        .tg-reviews .rv-avg{font:700 30px var(--fh,sans-serif);color:var(--acc,#2cb809);line-height:1}
        .tg-reviews .rv-stars i{color:var(--acc,#2cb809);font-size:14px}
        .tg-reviews .rv-stars i.off{color:var(--bd,#ddd)}
        .tg-reviews .rv-count{color:var(--mut,#888);font-size:13px}
        .tg-reviews .rv-item{background:var(--surf,#fff);border:1.5px solid var(--bd,rgba(0,0,0,.08));border-radius:14px;padding:14px;margin-bottom:10px}
        .tg-reviews .rv-item .nm{font:700 14.5px var(--fh,sans-serif)}
        .tg-reviews .rv-item .cm{color:var(--mut,#888);font-size:14px;margin-top:6px;white-space:pre-line}
        .tg-reviews .rv-reply{margin-top:10px;padding:10px 12px;border-left:3px solid var(--acc,#2cb809);background:rgba(0,0,0,.03);border-radius:8px;font-size:13.5px}
        .tg-reviews .rv-reply b{font-family:var(--fh,sans-serif)}
        .tg-reviews .rate{display:flex;flex-direction:row-reverse;justify-content:flex-end;gap:4px;margin:6px 0 10px}
        .tg-reviews .rate input{display:none}
        .tg-reviews .rate label{font-size:30px;color:var(--bd,#ddd);cursor:pointer;transition:color .12s}
        .tg-reviews .rate label:hover,.tg-reviews .rate label:hover ~ label,
        .tg-reviews .rate input:checked ~ label{color:var(--acc,#2cb809)}
        .tg-reviews .rv-cin{width:100%;padding:12px 14px;border:1.5px solid var(--bd,rgba(0,0,0,.08));border-radius:12px;font:15px var(--fb,sans-serif);background:var(--surf,#fff);color:var(--fg,#0a0a0a);margin-bottom:10px}
        .tg-reviews .rv-cin:focus{outline:0;border-color:var(--acc,#2cb809)}
        .tg-reviews .rv-btn{width:100%;border:0;background:var(--acc,#2cb809);color:#fff;border-radius:13px;padding:14px;font:700 15px var(--fh,sans-serif);cursor:pointer}
        .tg-reviews .rv-btn:disabled{opacity:.6}
        .tg-reviews .rv-thanks{display:none;background:var(--surf,#fff);border:1.5px solid var(--acc,#2cb809);border-radius:13px;padding:16px;text-align:center;color:var(--fg,#0a0a0a)}
    </style>

    <h2 style="font:700 18px var(--fh,sans-serif);margin-bottom:14px">{{ __('Avis clients') }}</h2>

    @if($rvCount > 0)
        <div class="rv-head">
            <div class="rv-avg">{{ number_format($rvAvg, 1) }}</div>
            <div>
                <div class="rv-stars">
                    @for($i = 1; $i <= 5; $i++)
                        <i class="fa-solid fa-star {{ $i <= round($rvAvg) ? '' : 'off' }}"></i>
                    @endfor
                </div>
                <div class="rv-count">{{ $rvCount }} {{ __('avis') }}</div>
            </div>
        </div>

        @foreach($reviews as $rv)
            <div class="rv-item">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <span class="nm">{{ $rv->author_name }}</span>
                    <span class="rv-stars">
                        @for($i = 1; $i <= 5; $i++)<i class="fa-solid fa-star {{ $i <= $rv->stars ? '' : 'off' }}"></i>@endfor
                    </span>
                </div>
                @if($rv->comment)<div class="cm">{{ $rv->comment }}</div>@endif
                @if($rv->reply)<div class="rv-reply"><b>{{ __('Réponse du marchand') }} :</b> {{ $rv->reply }}</div>@endif
            </div>
        @endforeach
    @else
        <p class="rv-count" style="margin-bottom:12px">{{ __('Soyez le premier à laisser un avis.') }}</p>
    @endif

    {{-- Formulaire d'avis --}}
    <form id="rvForm" onsubmit="submitReview(event)" style="margin-top:8px">
        <div class="rate">
            @for($i = 5; $i >= 1; $i--)
                <input type="radio" name="rv_rating" id="rv{{ $i }}" value="{{ $i }}" @if($i===5) checked @endif><label for="rv{{ $i }}">★</label>
            @endfor
        </div>
        <input class="rv-cin" id="rvName" maxlength="120" placeholder="{{ __('Votre nom') }}" required>
        <input class="rv-cin" id="rvPhone" type="tel" maxlength="40" placeholder="{{ __('Téléphone (optionnel)') }}">
        <textarea class="rv-cin" id="rvComment" rows="3" maxlength="1000" placeholder="{{ __('Votre commentaire (optionnel)') }}"></textarea>
        <button type="submit" class="rv-btn" id="rvBtn">{{ __('Envoyer mon avis') }}</button>
    </form>
    <div class="rv-thanks" id="rvThanks"><i class="fa-solid fa-circle-check" style="color:var(--acc,#2cb809)"></i> {{ __('Merci ! Votre avis sera publié après validation.') }}</div>
</section>

<script>
(function(){
    var URL = @json(route('tagtoa.reviews.store'));
    var CSRF = (document.querySelector('meta[name=csrf-token]')||{}).getAttribute ? document.querySelector('meta[name=csrf-token]').getAttribute('content') : '';
    var SUBJECT = {type:@json($subjectType), id:@json((int) $subjectId)};
    var T = {wait:@json(__('Patientez…')), send:@json(__('Envoyer mon avis')), err:@json(__('Réessayez.'))};
    var RV_UUID = 'rv-' + Date.now().toString(36) + Math.random().toString(36).slice(2,10);

    window.submitReview = function(e){
        e.preventDefault();
        var btn=document.getElementById('rvBtn'); btn.disabled=true; btn.textContent=T.wait;
        var rating=(document.querySelector('input[name=rv_rating]:checked')||{}).value || 5;
        var payload={
            subject_type:SUBJECT.type, subject_id:SUBJECT.id, rating:Number(rating),
            author_name:(document.getElementById('rvName').value||'').trim(),
            author_phone:(document.getElementById('rvPhone').value||'').trim(),
            comment:(document.getElementById('rvComment').value||'').trim(),
            client_uuid:RV_UUID
        };
        fetch(URL,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify(payload)})
        .then(function(r){return r.json().then(function(j){return {ok:r.ok,j:j};});})
        .then(function(res){
            if(!res.ok||!res.j.ok){ throw new Error(res.j&&res.j.message?res.j.message:''); }
            document.getElementById('rvForm').style.display='none';
            document.getElementById('rvThanks').style.display='block';
        })
        .catch(function(err){ btn.disabled=false; btn.textContent=T.send; alert(err.message||T.err); });
    };
})();
</script>
