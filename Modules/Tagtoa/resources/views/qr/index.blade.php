@extends('tagtoa::layouts.dashboard')
@php use Modules\Tagtoa\App\Support\Qr; @endphp
@section('title', __('QR & Partage'))
@section('page', __('QR & Partage'))

@section('content')
<p style="color:var(--muted);font-size:14px;margin-bottom:18px">{{ __('Téléchargez ou imprimez le QR code de chaque page publique. Vos clients scannent pour y accéder.') }}</p>

@if(empty($items))
    <div class="card"><div class="empty"><i class="fa-solid fa-qrcode"></i>{{ __('Aucune page publique pour l\'instant. Créez un site, un menu ou une page de paiement.') }}</div></div>
@else
    <div class="grid g3">
        @foreach($items as $i)
            @php $svg = Qr::svg($i['url'], 200); @endphp
            <div class="card" style="text-align:center">
                <div style="display:flex;align-items:center;justify-content:center;gap:8px;color:var(--muted);font:600 12px var(--fh);text-transform:uppercase;letter-spacing:.06em">
                    <i class="fa-solid {{ $i['icon'] }}"></i> {{ __($i['label']) }}
                </div>
                <b style="font-family:var(--fh);font-size:15px;display:block;margin:6px 0 12px">{{ $i['name'] }}</b>
                <div class="qrbox" style="background:#fff;border:1px solid var(--bd);border-radius:14px;padding:12px;display:inline-block;line-height:0">
                    @if($svg)
                        {!! $svg !!}
                    @else
                        <img src="{{ Qr::imgUrl($i['url'], 200) }}" alt="QR" width="200" height="200">
                    @endif
                </div>
                <div style="font-size:12px;color:var(--blue);margin-top:10px;word-break:break-all">{{ $i['url'] }}</div>
                <div class="row" style="margin-top:14px;gap:8px;justify-content:center">
                    <button type="button" class="btn btn-o btn-sm" style="flex:0" onclick="dlQr(this,'{{ $i['type'] }}-{{ $i['alias'] }}')"><i class="fa-solid fa-download"></i> {{ __('Télécharger') }}</button>
                    <a class="btn btn-d btn-sm" style="flex:0" href="{{ route('tagtoa.qr.poster',[$i['type'],$i['id']]) }}" target="_blank"><i class="fa-solid fa-print"></i> {{ __('Affiche') }}</a>
                    <button type="button" class="btn btn-o btn-sm" style="flex:0" data-copy="{{ $i['url'] }}" onclick="cpUrl(this)"><i class="fa-solid fa-copy"></i></button>
                </div>
            </div>
        @endforeach
    </div>
@endif

@push('scripts')
<script>
function dlQr(btn, name){
    var box = btn.closest('.card').querySelector('.qrbox');
    var svg = box.querySelector('svg');
    if(svg){
        var data = new XMLSerializer().serializeToString(svg);
        var blob = new Blob([data], {type:'image/svg+xml'});
        var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'qr-'+name+'.svg'; a.click();
        setTimeout(function(){URL.revokeObjectURL(a.href);},1000);
    } else {
        var img = box.querySelector('img'); if(img) window.open(img.src,'_blank');
    }
}
function cpUrl(btn){var t=btn.getAttribute('data-copy');navigator.clipboard&&navigator.clipboard.writeText(t);var o=btn.innerHTML;btn.innerHTML='<i class="fa-solid fa-check"></i>';setTimeout(function(){btn.innerHTML=o;},1200);}
</script>
@endpush
@endsection
