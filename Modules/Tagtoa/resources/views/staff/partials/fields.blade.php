{{-- Champs d'une fiche employé. Sert à la création ET à la modification :
     $staff est null quand on ajoute quelqu'un.

     Le code n'est JAMAIS réaffiché ni pré-rempli. En modification, laisser le
     champ vide garde le code que la personne connaît déjà — corriger un
     téléphone ne doit pas l'obliger à réapprendre son code. --}}
@php $edit = $staff !== null; @endphp

<div class="row">
    <div>
        <label class="lbl">{{ __('Nom') }} *</label>
        <input class="inp" name="name" maxlength="120" required
               value="{{ old('name', $staff->name ?? '') }}" placeholder="{{ __('Prénom et nom') }}">
    </div>
    <div>
        <label class="lbl">{{ __('Rôle') }} *</label>
        <select class="sel" name="role">
            @foreach($roles as $cle => $libelle)
                <option value="{{ $cle }}" @selected(old('role', $staff->role ?? 'cashier') === $cle)>{{ __($libelle) }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="row" style="margin-top:8px">
    <div>
        <label class="lbl">{{ __('Téléphone') }}</label>
        <input class="inp" name="phone" maxlength="40" inputmode="tel"
               value="{{ old('phone', $staff->phone ?? '') }}" placeholder="+509…">
    </div>
    <div>
        <label class="lbl">{{ __('E-mail') }} <span style="font-weight:400;color:var(--muted)">({{ __('optionnel') }})</span></label>
        <input class="inp" name="email" type="email" maxlength="190"
               value="{{ old('email', $staff->email ?? '') }}">
    </div>
</div>

<div class="row" style="margin-top:8px">
    <div>
        <label class="lbl">{{ __('Caisse habituelle') }} <span style="font-weight:400;color:var(--muted)">({{ __('optionnel') }})</span></label>
        <select class="sel" name="terminal_id">
            <option value="">{{ __('Toutes les caisses') }}</option>
            @foreach($terminals as $t)
                <option value="{{ $t->id }}" @selected((int) old('terminal_id', $staff->terminal_id ?? 0) === $t->id)>{{ $t->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="lbl">
            {{ __('Code d\'accès') }}
            @if($edit)
                <span style="font-weight:400;color:var(--muted)">({{ __('laisser vide = inchangé') }})</span>
            @else
                *
            @endif
        </label>
        <input class="inp" name="pin" type="password" inputmode="numeric" autocomplete="new-password"
               maxlength="6" pattern="[0-9]*" placeholder="{{ $edit ? '••••' : __('4 chiffres') }}"
               @if(! $edit) required @endif
               style="letter-spacing:.25em;font-size:17px">
    </div>
</div>

@if($errors->any())
    <div style="margin-top:10px;color:var(--red);font-size:13.5px">
        @foreach($errors->all() as $erreur)<div>{{ $erreur }}</div>@endforeach
    </div>
@endif
