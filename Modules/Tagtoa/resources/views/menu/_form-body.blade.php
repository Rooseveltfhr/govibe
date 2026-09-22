<form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('tagtoa.menu.dashboard.update',$menu->id) : route('tagtoa.menu.dashboard.store') }}">
    @csrf @if($editing) @method('PUT') @endif

    {{-- ----- Établissement : identité ----- --}}
    <div class="card" data-step="1">
        <div class="h-row"><h2>{{ __('Établissement') }}</h2></div>
        @unless($editing)
            {{-- Le commerce porte déjà logo/type/contact/devise : ce menu les
                 reprend pour que rien ne soit tapé deux fois. Toujours
                 modifiable — ce n'est qu'un point de départ. --}}
            <p style="color:var(--muted);font-size:13px;margin-top:-8px;margin-bottom:14px">
                {{ __('Logo, type, contact et devise sont pré-remplis depuis votre commerce — modifiez-les si ce menu est différent.') }}
            </p>
        @endunless
        <div class="row">
            <div><label class="lbl">{{ __('Nom') }}</label><input class="inp" name="name" value="{{ old('name',$menu->name) }}" placeholder="{{ __('Ex. Lounge 509') }}" required></div>
            <div class="type-select-wrap"><label class="lbl">{{ __('Type') }}</label><select class="sel" name="type">@foreach(\Modules\Tagtoa\App\Models\Menu\Menu::TYPES as $k=>$v)<option value="{{ $k }}" @selected(old('type',$menu->type ?: 'restaurant')===$k)>{{ __($v['label']) }}</option>@endforeach</select></div>
        </div>
        @if($isWizard ?? false)
            {{-- Grille de cartes : la même valeur que le <select> ci-dessus,
                 juste une autre façon de la choisir — utile sur l'assistant,
                 où le type se choisit en premier et seul sur l'écran. Le
                 formulaire classique ne la rend pas du tout : personne n'a
                 demandé à changer son <select>. --}}
            <div class="type-grid" id="typeGrid">
                @foreach(\Modules\Tagtoa\App\Models\Menu\Menu::TYPES as $k=>$v)
                    <button type="button" class="type-card" data-type="{{ $k }}" onclick="choseType('{{ $k }}')">
                        <i class="{{ $v['icon'] }}"></i>
                        <span class="type-card-label">{{ __($v['label']) }}</span>
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ----- Établissement : informations ----- --}}
    <div class="card" data-step="2">
        <div class="h-row"><h2>{{ __('Informations') }}</h2></div>
        <label class="lbl">{{ __('Alias (URL)') }}</label>
        <div style="display:flex;align-items:center;gap:8px"><span style="color:var(--muted);font-size:14px">tagtoa.com/menu/</span><input class="inp" name="alias" value="{{ old('alias',$menu->alias) }}" placeholder="{{ __('auto si vide') }}"></div>
        <label class="lbl">{{ __('Slogan') }}</label><input class="inp" name="tagline" value="{{ old('tagline',$menu->tagline) }}" placeholder="{{ __('Cuisine créole • Ambiance lounge') }}">
        <label class="lbl">{{ __('Description') }}</label><textarea class="inp" name="description" rows="2" maxlength="600">{{ old('description',$menu->description) }}</textarea>

        {{-- TRADUCTIONS — le client choisit sa langue, vous écrivez une fois.
             Le champ ci-dessus reste VOTRE langue, celle que vous tapez sans y
             penser. Ici, seulement pour qui veut aussi accueillir un client qui
             lit en anglais ou en espagnol — repliable, jamais imposé. --}}
        <details style="margin-top:10px">
            <summary style="cursor:pointer;font:600 13px var(--fh,inherit);color:var(--muted)">
                <i class="fa-solid fa-language"></i> {{ __('Traductions du slogan et de la description') }}
            </summary>
            <div style="margin-top:10px;display:flex;flex-direction:column;gap:10px">
                @foreach(\Modules\Tagtoa\App\Support\Locale::all() as $code => $meta)
                    @continue($code === \Modules\Tagtoa\App\Support\Locale::default())
                    <div style="border-top:1px dashed var(--bd);padding-top:10px">
                        <label class="lbl">{{ $meta['flag'] }} {{ $meta['label'] }} — {{ __('Slogan') }}</label>
                        <input class="inp" name="translations[{{ $code }}][tagline]" maxlength="160"
                               value="{{ old("translations.$code.tagline", $menu->translations[$code]['tagline'] ?? '') }}">
                        <label class="lbl">{{ $meta['flag'] }} {{ $meta['label'] }} — {{ __('Description') }}</label>
                        <textarea class="inp" name="translations[{{ $code }}][description]" rows="2" maxlength="600">{{ old("translations.$code.description", $menu->translations[$code]['description'] ?? '') }}</textarea>
                    </div>
                @endforeach
            </div>
        </details>
        <input type="hidden" name="translations_sent" value="1">
        <div class="row">
            <div><label class="lbl">{{ __('Logo') }}</label><input class="inp" type="file" name="logo" accept="image/*">@if($menu->logo_url)<img src="{{ $menu->logo_url }}" style="height:42px;border-radius:10px;margin-top:8px">@endif</div>
            <div><label class="lbl">{{ __('Couverture') }}</label><input class="inp" type="file" name="cover" accept="image/*">@if($editing && $menu->cover_url)<img src="{{ $menu->cover_url }}" style="height:42px;border-radius:10px;margin-top:8px">@endif</div>
        </div>
    </div>

    {{-- ----- Contact & commande ----- --}}
    <div class="card" data-step="3">
        <div class="h-row"><h2>{{ __('Contact & commande') }}</h2></div>
        <div class="row">
            <div><label class="lbl">{{ __('WhatsApp (commande)') }}</label><input class="inp" name="whatsapp" value="{{ old('whatsapp',$menu->whatsapp) }}" placeholder="+509 0000 0000"></div>
            <div><label class="lbl">{{ __('Téléphone') }}</label><input class="inp" name="phone" value="{{ old('phone',$menu->phone) }}" placeholder="+509 0000 0000"></div>
        </div>
        <label class="lbl">{{ __('Adresse') }}</label><input class="inp" name="address" value="{{ old('address',$menu->address) }}" placeholder="{{ __('Rue, ville') }}">
        <div class="row">
            <div><label class="lbl">{{ __('Devise') }}</label><select class="sel" name="currency">@foreach(\Modules\Tagtoa\App\Support\Money::options() as $code=>$label)<option value="{{ $code }}" @selected(old('currency',$menu->currency ?: \Modules\Tagtoa\App\Support\Locale::currencyFor())===$code)>{{ $label }}</option>@endforeach</select></div>
            <div><label class="lbl">{{ __('Page de paiement (TAGTOA Pay)') }}</label><select class="sel" name="pay_page_id"><option value="">{{ __('— Aucune —') }}</option>@foreach($payPages as $pp)<option value="{{ $pp->id }}" @selected(old('pay_page_id',$menu->pay_page_id)==$pp->id)>{{ $pp->title ?: $pp->alias }}</option>@endforeach</select></div>
        </div>
        <label class="switch"><input type="hidden" name="ordering_enabled" value="0"><input type="checkbox" name="ordering_enabled" value="1" @checked(old('ordering_enabled',$menu->ordering_enabled ?? true))> {{ __('Activer la commande WhatsApp') }}</label>

        {{-- Modes de service proposés au client — un sous-ensemble des trois
             modes de TAGTOA. Rien de coché nulle part (menu jamais réglé) =
             les trois restent offerts, comme avant ce réglage. --}}
        <label class="lbl" style="margin-top:10px">{{ __('Modes de service offerts') }}</label>
        <div class="chipwrap">
            @php $modesMenu = old('service_types', $menu->service_types ?? \Modules\Tagtoa\App\Models\Menu\Order::ORDER_TYPES); @endphp
            @foreach(\Modules\Tagtoa\App\Models\Menu\Order::ORDER_TYPE_LABELS as $code => $label)
                <label class="chip">
                    <input type="checkbox" name="service_types[]" value="{{ $code }}" @checked(in_array($code, $modesMenu, true))>
                    <span>{{ __($label) }}</span>
                </label>
            @endforeach
        </div>

        <label class="lbl" style="margin-top:10px">{{ __('Frais de livraison') }} <span style="font-weight:400;color:var(--muted)">({{ __('vide ou 0 = livraison gratuite') }})</span></label>
        <input class="inp" type="number" step="0.01" min="0" name="delivery_fee" value="{{ old('delivery_fee',$menu->delivery_fee) }}" placeholder="0.00" style="max-width:160px">
        <p style="color:var(--muted);font-size:13px;margin-top:10px">
            {{ __('Ce frais s\'applique par défaut. Un commerce qui livre dans plusieurs zones (centre-ville, périphérie…) peut définir un prix différent par zone ci-dessous — le client choisit la sienne, et son prix remplace le frais unique.') }}
        </p>
        <div class="h-row" style="margin-top:4px">
            <label class="lbl" style="margin-top:0">{{ __('Zones de livraison (optionnel)') }}</label>
            <button type="button" class="btn btn-o btn-sm" onclick="addZone()"><i class="fa-solid fa-plus"></i> {{ __('Zone') }}</button>
        </div>
        <div id="zones"></div>
    </div>

    {{-- ----- Apparence ----- --}}
    <div class="card" data-step="3">
        <div class="h-row"><h2>{{ __('Apparence') }}</h2></div>
        <div class="row">
            <div><label class="lbl">{{ __('Thème') }}</label><select class="sel" name="theme">@foreach(['light'=>'Clair','dark'=>'Sombre'] as $k=>$v)<option value="{{ $k }}" @selected(old('theme',$menu->theme ?: 'light')===$k)>{{ __($v) }}</option>@endforeach</select></div>
            <div><label class="lbl">{{ __('Couleur d\'accent') }}</label><input class="inp" type="color" name="accent_color" value="{{ old('accent_color',$menu->accent_color ?: '#2cb809') }}" style="height:48px;padding:6px"></div>
        </div>
        <label class="switch"><input type="hidden" name="show_prices" value="0"><input type="checkbox" name="show_prices" value="1" @checked(old('show_prices',$menu->show_prices ?? true))> {{ __('Afficher les prix') }}</label>
        <label class="switch"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$menu->is_active ?? true))> {{ __('Menu actif (visible au public)') }}</label>

        {{-- Langues offertes au client — un sous-ensemble des langues de
             TAGTOA. La langue par défaut reste toujours cochée et désactivée :
             le contenu de base (nom, description non traduits) est écrit
             dedans, elle ne peut pas être retirée. --}}
        <label class="lbl" style="margin-top:10px">{{ __('Langues du menu') }}</label>
        <div class="chipwrap">
            @php $languesMenu = old('languages', $menu->languages ?? \Modules\Tagtoa\App\Support\Locale::codes()); @endphp
            @foreach(\Modules\Tagtoa\App\Support\Locale::all() as $code => $meta)
                @php $estDefaut = $code === \Modules\Tagtoa\App\Support\Locale::default(); @endphp
                <label class="chip">
                    <input type="checkbox" name="languages[]" value="{{ $code }}"
                           @checked(in_array($code, $languesMenu, true) || $estDefaut) @disabled($estDefaut)>
                    <span>{{ $meta['flag'] }} {{ $meta['label'] }}</span>
                </label>
                {{-- Un champ désactivé n'est jamais soumis : on le rejoue en
                     caché pour que la langue par défaut arrive quand même
                     dans $_POST['languages']. --}}
                @if($estDefaut)<input type="hidden" name="languages[]" value="{{ $code }}">@endif
            @endforeach
        </div>
    </div>

    {{-- ----- Horaires ----- --}}
    <div class="card" data-step="3">
        <div class="h-row"><h2>{{ __('Horaires d\'ouverture') }}</h2></div>
        <label class="switch"><input type="hidden" name="show_hours" value="0"><input type="checkbox" name="show_hours" value="1" @checked(old('show_hours',$menu->show_hours ?? false))> {{ __('Afficher les horaires sur le menu public') }}</label>
        <p style="color:var(--muted);font-size:13px;margin-top:4px">
            {{ __('Sans horaire renseigné pour un jour, le commerce reste ouvert ce jour-là. Une commande est toujours refusée en dehors des heures indiquées, que cette case soit cochée ou non.') }}
        </p>
        <label class="lbl">{{ __('Fuseau horaire') }}</label>
        <select class="sel" name="timezone">
            @foreach(['America/Port-au-Prince','America/New_York','America/Santo_Domingo','America/Nassau','America/Toronto','Europe/Paris','America/Miquelon'] as $tz)
                <option value="{{ $tz }}" @selected(old('timezone',$menu->timezone ?: 'America/Port-au-Prince')===$tz)>{{ $tz }}</option>
            @endforeach
        </select>
        <div style="display:flex;flex-direction:column;gap:8px;margin-top:12px">
            @foreach(\Modules\Tagtoa\App\Support\Menu\BusinessHours::DAYS as $jour)
                @php
                    $joursLabels = ['mon'=>__('Lundi'),'tue'=>__('Mardi'),'wed'=>__('Mercredi'),'thu'=>__('Jeudi'),'fri'=>__('Vendredi'),'sat'=>__('Samedi'),'sun'=>__('Dimanche')];
                    $plage = $menu->hours[$jour] ?? null;
                    // Un menu qui n'a JAMAIS renseigné d'horaires ne doit pas
                    // afficher les sept jours cochés « Fermé » — ça alarmerait
                    // pour rien. Seul un menu qui a déjà des horaires ($menu->hours
                    // non nul) sait vraiment distinguer un jour fermé d'un jour
                    // simplement pas encore rempli.
                    $fermeParDefaut = $menu->hours !== null && ! $plage;
                @endphp
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <span style="min-width:88px;font-weight:600;font-size:13.5px">{{ $joursLabels[$jour] }}</span>
                    <label class="switch" style="flex:0">
                        <input type="checkbox" name="hours[{{ $jour }}][closed]" value="1" @checked(old("hours.$jour.closed", $fermeParDefaut))
                               onchange="this.closest('div').querySelectorAll('input[type=time]').forEach(function(i){ i.disabled = this.checked; }, this)">
                        {{ __('Fermé') }}
                    </label>
                    <input class="inp" type="time" name="hours[{{ $jour }}][open]" value="{{ old('hours.'.$jour.'.open', $plage['open'] ?? '') }}" style="max-width:120px" @disabled(old("hours.$jour.closed", $fermeParDefaut))>
                    <span>—</span>
                    <input class="inp" type="time" name="hours[{{ $jour }}][close]" value="{{ old('hours.'.$jour.'.close', $plage['close'] ?? '') }}" style="max-width:120px" @disabled(old("hours.$jour.closed", $fermeParDefaut))>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ----- Catégories & produits ----- --}}
    <div class="card" data-step="4 5">
        <div class="h-row">
            <h2>{{ __('Catégories &') }} <span class="tt-nouns">{{ __('Produits') }}</span></h2>
            <button type="button" class="btn btn-d btn-sm" onclick="addCat()"><i class="fa-solid fa-plus"></i> {{ __('Catégorie') }}</button>
        </div>
        <p style="color:var(--muted);font-size:13px;margin-top:-8px">
            {{ __('Le formulaire suit le type d\'établissement choisi plus haut : un hôtel décrit des chambres, un bar des boissons, un restaurant des plats.') }}
        </p>
        {{-- Catégories proposées pour ce métier : un raccourci, jamais imposé. --}}
        <div id="catpresets" style="display:flex;gap:7px;flex-wrap:wrap;margin-bottom:4px"></div>
        <div id="cats"></div>
    </div>

    <button class="btn btn-p" data-step="7"><i class="fa-solid fa-floppy-disk"></i> {{ __('Enregistrer le menu') }}</button>

    {{-- TOUT DERNIER champ du formulaire, volontairement. PHP coupe $_POST
         au-delà de max_input_vars sans rien dire : si ce jeton n'arrive pas,
         c'est que la fin de l'envoi a été perdue et le serveur refuse
         d'enregistrer à moitié. Ne rien mettre après lui. --}}
    <input type="hidden" name="form_end" value="1">
</form>

{{-- Supprimer est une action à part, jamais un effet de bord de
     l'enregistrement : un envoi incomplet ne doit pas valoir suppression. --}}
<form id="delform" method="POST" style="display:none">@csrf @method('DELETE')</form>

{{-- Template catégorie --}}
<template id="cattpl">
    <div class="catblock" data-ci="CIDX" style="border:1.5px solid var(--bd);border-radius:14px;padding:14px;margin-top:12px;background:#fafafa">
        <div style="display:flex;gap:8px;align-items:center">
            {{-- Pas de champ icône : elle est déduite automatiquement du nom
                 (« Boissons » → un verre, « Desserts » → un gâteau…), pour que
                 le formulaire reste simple — personne ne sait quelle classe
                 Font Awesome choisir. --}}
            <input name="cats[CIDX][name]" class="inp" placeholder="{{ __('Nom de la catégorie') }}" style="font-weight:600">
            <button type="button" class="btn btn-o btn-sm delcat" style="flex:0;color:var(--red)"
                    title="{{ __('Supprimer la catégorie') }}"><i class="fa-solid fa-trash"></i></button>
        </div>
        <button type="button" class="btn btn-o btn-sm togcattr" style="margin-top:8px">
            <i class="fa-solid fa-language"></i> {{ __('Traductions du nom') }}
        </button>
        <div class="cattr" hidden style="display:flex;flex-direction:column;gap:6px;margin-top:8px;padding-top:8px;border-top:1px dashed var(--bd)">
            @foreach(\Modules\Tagtoa\App\Support\Locale::all() as $code => $meta)
                @continue($code === \Modules\Tagtoa\App\Support\Locale::default())
                <input class="inp" name="cats[CIDX][translations][{{ $code }}][name]"
                       placeholder="{{ $meta['flag'] }} {{ $meta['label'] }} — {{ __('nom de la catégorie') }}">
            @endforeach
        </div>
        <input type="hidden" name="cats[CIDX][translations_sent]" value="1">
        <div class="items" style="margin-top:10px"></div>
        <button type="button" class="btn btn-o btn-sm tt-additem" onclick="addItem(this.closest('.catblock'))" style="margin-top:6px"><i class="fa-solid fa-plus"></i> {{ __('Ajouter') }}</button>
    </div>
</template>

{{-- Template produit/service --}}
<template id="itemtpl">
    <div class="itemrow" data-ci="CIDX" data-ii="IIDX" style="background:#fff;border:1px solid var(--bd);border-radius:11px;padding:10px;margin-bottom:8px">
        <div style="display:flex;gap:8px;align-items:center">
            <input name="cats[CIDX][items][IIDX][name]" class="inp tt-itemname" placeholder="{{ __('Nom') }}">
            <input name="cats[CIDX][items][IIDX][price]" class="inp tt-price" type="number" step="0.01" min="0" placeholder="{{ __('Prix') }}" style="max-width:130px">
            <button type="button" class="btn btn-o btn-sm delitem" style="flex:0;color:var(--red)"
                    title="{{ __('Supprimer l\'article') }}"><i class="fa-solid fa-trash"></i></button>
        </div>
        <input name="cats[CIDX][items][IIDX][description]" class="inp" placeholder="{{ __('Description (optionnel)') }}" style="margin-top:8px">
        <div style="display:flex;gap:16px;align-items:center;margin-top:8px;flex-wrap:wrap">
            <div style="display:flex;align-items:center;gap:8px">
                <img class="itemphoto" style="height:40px;width:40px;border-radius:8px;object-fit:cover;display:none">
                {{-- `capture="environment"` ouvre directement l'appareil photo
                     arrière sur téléphone, sans empêcher de choisir une photo
                     déjà prise dans la galerie. --}}
                <input name="cats[CIDX][items][IIDX][image]" class="inp" type="file" accept="image/*" capture="environment" style="max-width:190px" onchange="previewItemImage(this)">
                <label class="switch removeimgwrap" style="flex:0;display:none"><input type="checkbox" name="cats[CIDX][items][IIDX][remove_image]" value="1"> {{ __('Retirer') }}</label>
            </div>
            <input name="cats[CIDX][items][IIDX][badge]" class="inp" placeholder="{{ __('Badge: Nouveau, Promo…') }}" style="max-width:200px">
            {{-- Stock décimal : un plat peut se vendre à la livre (griot, poisson). --}}
            <input name="cats[CIDX][items][IIDX][stock]" class="inp" type="number" step="0.001" min="0" placeholder="{{ __('Stock (vide = illimité)') }}" style="max-width:170px" title="{{ __('Laisser vide pour ne pas suivre le stock') }}">
            <label class="switch" style="flex:0"><input type="hidden" name="cats[CIDX][items][IIDX][is_available]" value="0"><input type="checkbox" name="cats[CIDX][items][IIDX][is_available]" value="1" checked> {{ __('Disponible') }}</label>
            <label class="switch" style="flex:0"><input type="checkbox" name="cats[CIDX][items][IIDX][is_featured]" value="1"> {{ __('Mis en avant') }}</label>
        </div>
        {{-- Gestion : coût matière, unité, seuil, référence. Replié par défaut —
             un restaurant qui veut seulement afficher sa carte ne doit pas le
             subir ; celui qui veut savoir ce que chaque plat lui rapporte le
             déplie une fois. --}}
        <button type="button" class="btn btn-o btn-sm togdet" style="margin-top:8px">
            <i class="fa-solid fa-sliders"></i> {{ __('Coût & gestion') }}
        </button>
        <div class="itemdet" hidden style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-top:8px;padding-top:8px;border-top:1px dashed var(--bd)">
            <label style="font-size:12px;color:var(--muted)">{{ __('Coût matière') }}
                <input name="cats[CIDX][items][IIDX][cost_price]" class="inp" type="number" step="0.01" min="0" placeholder="{{ __('non renseigné') }}" style="max-width:130px">
            </label>
            <label style="font-size:12px;color:var(--muted)">{{ __('Unité') }}
                <select name="cats[CIDX][items][IIDX][unit]" class="inp" style="max-width:130px">
                    @foreach (\Modules\Tagtoa\App\Support\Catalog\Pricing::UNITS as $cle => $u)
                        <option value="{{ $cle }}">{{ __($u['label']) }}</option>
                    @endforeach
                </select>
            </label>
            <label style="font-size:12px;color:var(--muted)">{{ __('Alerte sous') }}
                <input name="cats[CIDX][items][IIDX][low_stock_threshold]" class="inp" type="number" step="0.001" min="0" placeholder="5" style="max-width:110px">
            </label>
            <label style="font-size:12px;color:var(--muted)">{{ __('Référence (SKU)') }}
                <input name="cats[CIDX][items][IIDX][sku]" class="inp" maxlength="60" style="max-width:140px">
            </label>
            {{-- Chez qui on rachète : pré-rempli à la saisie d'une réception. --}}
            <label style="font-size:12px;color:var(--muted)">{{ __('Fournisseur') }}
                <select name="cats[CIDX][items][IIDX][supplier_id]" class="inp" style="max-width:170px">
                    <option value="">—</option>
                    @foreach($suppliers as $f)
                        <option value="{{ $f->id }}">{{ $f->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        {{-- TRADUCTIONS — comme au menu : le champ « Nom » ci-dessus reste
             votre langue ; ceci n'est que pour le client qui lit ailleurs. --}}
        <button type="button" class="btn btn-o btn-sm togitemtr" style="margin-top:8px">
            <i class="fa-solid fa-language"></i> {{ __('Traductions') }}
        </button>
        <div class="itemtr" hidden style="display:flex;flex-direction:column;gap:8px;margin-top:8px;padding-top:8px;border-top:1px dashed var(--bd)">
            @foreach(\Modules\Tagtoa\App\Support\Locale::all() as $code => $meta)
                @continue($code === \Modules\Tagtoa\App\Support\Locale::default())
                <div>
                    <input class="inp" name="cats[CIDX][items][IIDX][translations][{{ $code }}][name]"
                           placeholder="{{ $meta['flag'] }} {{ $meta['label'] }} — {{ __('nom') }}" style="margin-bottom:4px">
                    <input class="inp" name="cats[CIDX][items][IIDX][translations][{{ $code }}][description]"
                           placeholder="{{ $meta['flag'] }} {{ $meta['label'] }} — {{ __('description') }}">
                </div>
            @endforeach
        </div>
        <input type="hidden" name="cats[CIDX][items][IIDX][translations_sent]" value="1">

        {{-- Champs propres au métier, injectés selon le type d'établissement. --}}
        <div class="specs"></div>
        {{-- Atteste que cette ligne a bien porté ses options : sans le marqueur,
             le serveur n'y touche pas plutôt que de les effacer. --}}
        <input type="hidden" name="cats[CIDX][items][IIDX][options_sent]" value="1">
        <div class="options" style="margin-top:8px"></div>
        <button type="button" class="btn btn-o btn-sm" onclick="addOption(this.closest('.itemrow'))" style="margin-top:6px"><i class="fa-solid fa-plus"></i> {{ __('Option (taille, extra…)') }}</button>
    </div>
</template>

{{-- Template groupe d'options (ex. Taille, Suppléments) --}}
<template id="opttpl">
    <div class="optblock" data-ci="CIDX" data-ii="IIDX" data-oi="OIDX" style="border:1px dashed var(--bd);border-radius:10px;padding:8px;margin-top:8px;background:#fafafa">
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <input name="cats[CIDX][items][IIDX][options][OIDX][name]" class="inp" placeholder="{{ __('Nom du groupe (ex. Taille)') }}" style="flex:1;min-width:140px">
            <label class="switch" style="flex:0"><input type="checkbox" name="cats[CIDX][items][IIDX][options][OIDX][required]" value="1"> {{ __('Obligatoire') }}</label>
            <label class="switch" style="flex:0"><input type="checkbox" name="cats[CIDX][items][IIDX][options][OIDX][multiple]" value="1"> {{ __('Choix multiples') }}</label>
            <button type="button" class="btn btn-o btn-sm" style="flex:0;color:var(--red)" onclick="this.closest('.optblock').remove()"><i class="fa-solid fa-trash"></i></button>
        </div>
        <div class="choices" style="margin-top:6px"></div>
        <button type="button" class="btn btn-o btn-sm" onclick="addChoice(this.closest('.optblock'))" style="margin-top:4px"><i class="fa-solid fa-plus"></i> {{ __('Choix') }}</button>
    </div>
</template>

{{-- Template choix d'un groupe d'options --}}
<template id="choicetpl">
    <div class="choicerow" style="display:flex;gap:8px;align-items:center;margin-top:4px">
        <input name="cats[CIDX][items][IIDX][options][OIDX][choices][CHIDX][label]" class="inp" placeholder="{{ __('Libellé (ex. Grand)') }}">
        <input name="cats[CIDX][items][IIDX][options][OIDX][choices][CHIDX][price_delta]" class="inp" type="number" step="0.01" placeholder="{{ __('+/- prix') }}" style="max-width:110px">
        <button type="button" class="btn btn-o btn-sm" style="flex:0;color:var(--red)" onclick="this.closest('.choicerow').remove()"><i class="fa-solid fa-trash"></i></button>
    </div>
</template>

{{-- Template zone de livraison --}}
<template id="zonetpl">
    <div class="zonerow" style="display:flex;gap:8px;align-items:center;margin-top:8px">
        <input name="delivery_zones[ZIDX][name]" class="inp" placeholder="{{ __('Nom (ex. Centre-ville)') }}">
        <input name="delivery_zones[ZIDX][fee]" class="inp" type="number" step="0.01" min="0" placeholder="{{ __('Frais') }}" style="max-width:130px">
        <button type="button" class="btn btn-o btn-sm" style="flex:0;color:var(--red)" onclick="this.closest('.zonerow').remove()"><i class="fa-solid fa-trash"></i></button>
    </div>
</template>

<style>
    /* Champs métier : une grille dense qui reste lisible sur téléphone. */
    .specgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px;margin-top:10px;
              padding-top:10px;border-top:1px dashed var(--bd)}
    .specfld .lbl{margin-top:0;font-size:12.5px}
    .tagwrap{display:flex;flex-wrap:wrap;gap:6px}
    .tag input{position:absolute;opacity:0;width:0;height:0}
    .tag span{display:inline-block;border:1.5px solid var(--bd);border-radius:999px;padding:5px 11px;
              font-size:12.5px;cursor:pointer;transition:.14s;user-select:none}
    .tag input:checked + span{border-color:#2cb809;background:rgba(44,184,9,.09);color:#0e5f44;font-weight:600}
    .tag input:focus-visible + span{outline:2px solid #2cb809;outline-offset:2px}
</style>
@push('scripts')
<script>
var cIdx = 0;

/* ------------------------------------------------------------------
   Le formulaire suit le métier.
   Les profils viennent du serveur (BusinessProfile) : le navigateur ne fait
   que les rendre. Ce qui sera réellement enregistré est de toute façon revalidé
   côté serveur contre le même profil — le JS est un confort, pas une garantie.
   ------------------------------------------------------------------ */
var PROFILES = @json(\Modules\Tagtoa\App\Support\Menu\BusinessProfile::PROFILES);

// Rayons courants d'un petit commerce, en plus des catégories du métier
// choisi — LA MÊME liste que celle proposée dans POS (CategoryPresets::COMMON) :
// sans liste unique, les deux écrans finiraient par diverger pour la même
// réalité.
var CATEGORY_PRESETS_COMMON = @json(\Modules\Tagtoa\App\Support\Catalog\CategoryPresets::COMMON);

function currentProfile(){
    var sel = document.querySelector('select[name="type"]');
    var t = sel ? sel.value : 'other';
    return PROFILES[t] || PROFILES['other'];
}

function esc(v){
    return String(v == null ? '' : v).replace(/[&<>"']/g, function(c){
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
}

/* Rend les champs métier d'UN article. `values` = ce qui est déjà enregistré. */
function renderSpecs(row, values){
    var box = row.querySelector('.specs');
    if (!box) { return; }
    var ci = row.getAttribute('data-ci'), ii = row.getAttribute('data-ii');
    var fields = currentProfile().fields || {};
    values = values || {};

    var html = '';
    Object.keys(fields).forEach(function(key){
        var f = fields[key];
        var base = 'cats['+ci+'][items]['+ii+'][specs]['+key+']';
        var v = values[key];
        html += '<div class="specfld"><label class="lbl">'+esc(f.label)
             + (f.unit ? ' <span style="font-weight:400;color:var(--muted)">('+esc(f.unit)+')</span>' : '')
             + '</label>';

        if (f.type === 'number'){
            html += '<input class="inp" type="number" step="any" name="'+base+'"'
                 + (f.min != null ? ' min="'+esc(f.min)+'"' : '')
                 + (f.max != null ? ' max="'+esc(f.max)+'"' : '')
                 + ' value="'+esc(v != null ? v : '')+'">';
        } else if (f.type === 'select'){
            html += '<select class="sel" name="'+base+'"><option value="">—</option>';
            (f.options || []).forEach(function(o){
                html += '<option value="'+esc(o)+'"'+(v === o ? ' selected' : '')+'>'+esc(o)+'</option>';
            });
            html += '</select>';
        } else if (f.type === 'tags'){
            var chosen = Array.isArray(v) ? v : [];
            html += '<div class="tagwrap">';
            (f.options || []).forEach(function(o){
                html += '<label class="tag"><input type="checkbox" name="'+base+'[]" value="'+esc(o)+'"'
                     + (chosen.indexOf(o) !== -1 ? ' checked' : '')+'><span>'+esc(o)+'</span></label>';
            });
            html += '</div>';
        } else if (f.type === 'bool'){
            html += '<label class="switch"><input type="checkbox" name="'+base+'" value="1"'
                 + (v ? ' checked' : '')+'> '+esc(f.label)+'</label>';
        } else {
            html += '<input class="inp" name="'+base+'" maxlength="'+esc(f.max || 120)+'" value="'+esc(v != null ? v : '')+'">';
        }
        html += '</div>';
    });

    box.innerHTML = html ? '<div class="specgrid">'+html+'</div>' : '';
}

/* Ce que l'utilisateur a saisi dans les champs métier d'un article. */
function readSpecs(row){
    var out = {};
    row.querySelectorAll('.specs [name]').forEach(function(el){
        var m = el.name.match(/\[specs\]\[([^\]]+)\]/);
        if (!m) { return; }
        var key = m[1];
        if (el.type === 'checkbox'){
            if (el.name.slice(-2) === '[]'){
                if (el.checked){ (out[key] = out[key] || []).push(el.value); }
            } else if (el.checked){ out[key] = true; }
        } else if (el.value !== ''){ out[key] = el.value; }
    });
    return out;
}

/* Changement de type : on re-rend tout en gardant ce qui a un sens dans le
   nouveau métier (une capacité de chambre n'a plus de place dans un restaurant,
   mais la valeur reste en base tant que l'article n'est pas ré-enregistré). */
function applyProfile(){
    var p = currentProfile();

    document.querySelectorAll('.tt-nouns').forEach(function(el){ el.textContent = p.nouns; });
    document.querySelectorAll('.tt-itemname').forEach(function(el){ el.placeholder = 'Nom — ' + p.noun; });
    document.querySelectorAll('.tt-price').forEach(function(el){ el.placeholder = p.price_hint; });
    document.querySelectorAll('.tt-additem').forEach(function(el){
        el.innerHTML = '<i class="fa-solid fa-plus"></i> ' + p.noun;
    });

    document.querySelectorAll('.itemrow').forEach(function(row){ renderSpecs(row, readSpecs(row)); });
    renderPresets();
    syncTypeGrid();
}

/* Reflète sur la grille de cartes (assistant) la valeur du <select> — seule
   source de vérité. Sans effet si la grille n'est pas dans la page. */
function syncTypeGrid(){
    var sel = document.querySelector('select[name="type"]');
    if (!sel) { return; }
    document.querySelectorAll('.type-card').forEach(function(card){
        card.classList.toggle('selected', card.getAttribute('data-type') === sel.value);
    });
}

/* Choix depuis la grille de cartes : répercuté sur le <select>, qui reste le
   seul champ réellement soumis — applyProfile() (déjà écouté sur le
   <select>) se charge du reste (rayons suggérés, champs métier…). */
function choseType(type){
    var sel = document.querySelector('select[name="type"]');
    if (!sel) { return; }
    sel.value = type;
    sel.dispatchEvent(new Event('change'));
}

/* Catégories proposées pour ce métier — un clic les ajoute, rien n'est imposé. */
function renderPresets(){
    var box = document.getElementById('catpresets');
    if (!box) { return; }
    var existing = Array.prototype.map.call(
        document.querySelectorAll('#cats [name$="[name]"].inp'),
        function(el){ return (el.value || '').toLowerCase().trim(); }
    );
    box.innerHTML = '';
    // Les catégories du métier D'ABORD (les plus pertinentes), puis les
    // rayons génériques — sans doublon entre les deux listes ni avec ce qui
    // est déjà ajouté.
    var dejaSuggere = [];
    (currentProfile().categories || []).concat(CATEGORY_PRESETS_COMMON).forEach(function(name){
        var cle = name.toLowerCase();
        if (existing.indexOf(cle) !== -1 || dejaSuggere.indexOf(cle) !== -1) { return; }
        dejaSuggere.push(cle);
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'btn btn-o btn-sm'; b.style.flex = '0';
        b.innerHTML = '<i class="fa-solid fa-plus"></i> ' + esc(name);
        b.onclick = function(){
            var block = addCat({ name: name });
            addItem(block);
            renderPresets();
        };
        box.appendChild(b);
    });
}

function previewItemImage(input){
    var row = input.closest('.itemrow');
    var img = row.querySelector('.itemphoto');
    if (input.files && input.files[0]){
        var reader = new FileReader();
        reader.onload = function(e){ img.src = e.target.result; img.style.display='inline-block'; };
        reader.readAsDataURL(input.files[0]);
    }
}

function addOption(itemRow, d){
    var ci = itemRow.getAttribute('data-ci');
    var ii = itemRow.getAttribute('data-ii');
    var oi = parseInt(itemRow.getAttribute('data-oi') || '0', 10);
    itemRow.setAttribute('data-oi', oi + 1);
    var html = document.getElementById('opttpl').innerHTML.replace(/CIDX/g, ci).replace(/IIDX/g, ii).replace(/OIDX/g, oi);
    var box = document.createElement('div'); box.innerHTML = html;
    var row = box.firstElementChild;
    itemRow.querySelector('.options').appendChild(row);
    if (d){
        row.querySelector('[name$="[name]"]').value = d.name || '';
        row.querySelector('[name$="[required]"]').checked = !!d.required;
        row.querySelector('[name$="[multiple]"]').checked = !!d.multiple;
        var h = document.createElement('input'); h.type='hidden'; h.name='cats['+ci+'][items]['+ii+'][options]['+oi+'][id]'; h.value=d.id; row.appendChild(h);
        (d.choices || []).forEach(function(ch){ addChoice(row, ch); });
    }
    return row;
}

function addChoice(optRow, d){
    var ci = optRow.getAttribute('data-ci');
    var ii = optRow.getAttribute('data-ii');
    var oi = optRow.getAttribute('data-oi');
    var chi = parseInt(optRow.getAttribute('data-chi') || '0', 10);
    optRow.setAttribute('data-chi', chi + 1);
    var html = document.getElementById('choicetpl').innerHTML.replace(/CIDX/g, ci).replace(/IIDX/g, ii).replace(/OIDX/g, oi).replace(/CHIDX/g, chi);
    var box = document.createElement('div'); box.innerHTML = html;
    var row = box.firstElementChild;
    optRow.querySelector('.choices').appendChild(row);
    if (d){
        row.querySelector('[name$="[label]"]').value = d.label || '';
        row.querySelector('[name$="[price_delta]"]').value = (d.price_delta != null ? d.price_delta : '');
        var h = document.createElement('input'); h.type='hidden'; h.name='cats['+ci+'][items]['+ii+'][options]['+oi+'][choices]['+chi+'][id]'; h.value=d.id; row.appendChild(h);
    }
    return row;
}

var DEL_ITEM_URL = @json($editing ? url('/tagtoa/menu/'.$menu->id.'/items') : null);
var DEL_CAT_URL  = @json($editing ? url('/tagtoa/menu/'.$menu->id.'/categories') : null);

/* Ligne jamais enregistrée → on l'enlève de l'écran.
   Élément déjà en base → suppression serveur, confirmée. Depuis que
   « Enregistrer » ne supprime plus rien, retirer la ligne de l'écran ne
   suffirait pas : le plat serait toujours là au rechargement. */
function supprimer(el, url, question){
    var champId = el.querySelector(':scope > input[name$="[id]"]');
    if (!champId || !champId.value || !url){ el.remove(); return; }

    if (!confirm(question)) return;

    var f = document.getElementById('delform');
    f.action = url + '/' + champId.value;
    f.submit();
}

function addItem(catEl, d){
    var ci = catEl.getAttribute('data-ci');
    var ii = parseInt(catEl.getAttribute('data-ii') || '0', 10);
    catEl.setAttribute('data-ii', ii + 1);
    var html = document.getElementById('itemtpl').innerHTML.replace(/CIDX/g, ci).replace(/IIDX/g, ii);
    var box = document.createElement('div'); box.innerHTML = html;
    var row = box.firstElementChild;
    catEl.querySelector('.items').appendChild(row);
    if (d){
        row.querySelector('[name$="[name]"]').value = d.name || '';
        row.querySelector('[name$="[price]"]').value = (d.price != null ? d.price : '');
        row.querySelector('[name$="[description]"]').value = d.description || '';
        row.querySelector('[name$="[badge]"]').value = d.badge || '';
        row.querySelector('[name$="[stock]"]').value = (d.stock != null ? d.stock : '');
        row.querySelector('[name$="[cost_price]"]').value = (d.cost_price != null ? d.cost_price : '');
        row.querySelector('[name$="[unit]"]').value = d.unit || 'piece';
        row.querySelector('[name$="[low_stock_threshold]"]').value = (d.low_stock_threshold != null ? d.low_stock_threshold : '');
        row.querySelector('[name$="[sku]"]').value = d.sku || '';
        row.querySelector('[name$="[supplier_id]"]').value = d.supplier_id || '';
        row.querySelector('input[type=checkbox][name$="[is_available]"]').checked = d.is_available !== false;
        row.querySelector('[name$="[is_featured]"]').checked = !!d.is_featured;
        var h = document.createElement('input'); h.type='hidden'; h.name='cats['+ci+'][items]['+ii+'][id]'; h.value=d.id; row.appendChild(h);
        if (d.image_url){
            row.querySelector('.itemphoto').src = d.image_url;
            row.querySelector('.itemphoto').style.display = 'inline-block';
            row.querySelector('.removeimgwrap').style.display = 'flex';
        }
        (d.options || []).forEach(function(o){ addOption(row, o); });
        // Traductions : un champ par langue connue, rempli seulement si le
        // plat en porte une. `querySelector` renvoie null pour une langue sans
        // champ dans le gabarit (ne devrait pas arriver, mais un menu du passé
        // ne doit jamais planter la page pour autant).
        var trad = d.translations || {};
        Object.keys(trad).forEach(function(langue){
            var nomEl = row.querySelector('[name="cats['+ci+'][items]['+ii+'][translations]['+langue+'][name]"]');
            var descEl = row.querySelector('[name="cats['+ci+'][items]['+ii+'][translations]['+langue+'][description]"]');
            if (nomEl) nomEl.value = trad[langue].name || '';
            if (descEl) descEl.value = trad[langue].description || '';
        });
    }
    row.querySelector('.togdet').addEventListener('click', function(){
        var det = row.querySelector('.itemdet'); det.hidden = !det.hidden;
    });
    row.querySelector('.togitemtr').addEventListener('click', function(){
        var tr = row.querySelector('.itemtr'); tr.hidden = !tr.hidden;
    });
    row.querySelector('.delitem').addEventListener('click', function(){
        var nom = (row.querySelector('[name$="[name]"]').value || '').trim();
        supprimer(row, DEL_ITEM_URL, "{{ __('Supprimer définitivement cet article ? Il disparaîtra aussi de la caisse.') }}\n\n" + nom);
    });
    renderSpecs(row, d ? d.specs : null);
    return row;
}

function addCat(d){
    var ci = cIdx++;
    var html = document.getElementById('cattpl').innerHTML.replace(/CIDX/g, ci);
    var box = document.createElement('div'); box.innerHTML = html;
    var block = box.firstElementChild;
    document.getElementById('cats').appendChild(block);
    if (d){
        block.querySelector('[name$="[name]"]').value = d.name || '';
        var h = document.createElement('input'); h.type='hidden'; h.name='cats['+ci+'][id]'; h.value=d.id; block.appendChild(h);
        var trad = d.translations || {};
        Object.keys(trad).forEach(function(langue){
            var nomEl = block.querySelector('[name="cats['+ci+'][translations]['+langue+'][name]"]');
            if (nomEl) nomEl.value = trad[langue].name || '';
        });
        (d.items || []).forEach(function(it){ addItem(block, it); });
    }
    block.querySelector('.togcattr').addEventListener('click', function(){
        var tr = block.querySelector('.cattr'); tr.hidden = !tr.hidden;
    });
    block.querySelector('.delcat').addEventListener('click', function(){
        var nom = (block.querySelector('[name$="[name]"]').value || '').trim();
        supprimer(block, DEL_CAT_URL, "{{ __('Supprimer cette catégorie ET tous ses articles ? Cette action est définitive.') }}\n\n" + nom);
    });
    return block;
}

var zIdx = 0;
function addZone(d){
    var zi = zIdx++;
    var html = document.getElementById('zonetpl').innerHTML.replace(/ZIDX/g, zi);
    var box = document.createElement('div'); box.innerHTML = html;
    var row = box.firstElementChild;
    document.getElementById('zones').appendChild(row);
    if (d){
        row.querySelector('[name$="[name]"]').value = d.name || '';
        row.querySelector('[name$="[fee]"]').value = (d.fee != null ? d.fee : '');
        var h = document.createElement('input'); h.type='hidden'; h.name='delivery_zones['+zi+'][id]'; h.value=d.id; row.appendChild(h);
    }
    return row;
}

@php
    $zoneData = $menu->relationLoaded('deliveryZones')
        ? $menu->deliveryZones->map(fn ($z) => ['id' => $z->id, 'name' => $z->name, 'fee' => $z->fee])->values()
        : [];
@endphp
var existingZones = @json($zoneData);
existingZones.forEach(addZone);

@php
    $catData = $menu->relationLoaded('categories')
        ? $menu->categories->map(fn ($c) => [
            'id' => $c->id, 'name' => $c->name, 'icon' => $c->icon,
            'translations' => $c->translations ?: (object) [],
            'items' => $c->items->map(fn ($i) => [
                'id' => $i->id, 'name' => $i->name, 'price' => $i->price,
                'description' => $i->description, 'badge' => $i->badge, 'is_available' => $i->is_available,
                'is_featured' => $i->is_featured, 'stock' => $i->stock, 'image_url' => $i->image_url,
                'cost_price' => $i->cost_price, 'unit' => $i->unit_key,
                'low_stock_threshold' => $i->low_stock_threshold, 'sku' => $i->sku,
                'supplier_id' => $i->supplier_id,
                'translations' => $i->translations ?: (object) [],
                'specs' => $i->specs ?: (object) [],
                'options' => $i->options->map(fn ($o) => [
                    'id' => $o->id, 'name' => $o->name, 'required' => $o->required, 'multiple' => $o->multiple,
                    'choices' => $o->choices->map(fn ($ch) => ['id' => $ch->id, 'label' => $ch->label, 'price_delta' => $ch->price_delta])->values(),
                ])->values(),
            ])->values(),
        ])->values()
        : [];
@endphp
var existing = @json($catData);

if (existing.length){ existing.forEach(addCat); }
else { var c = addCat(); addItem(c); }

// Le formulaire suit le type dès qu'on en change, sans recharger la page.
var typeSel = document.querySelector('select[name="type"]');
if (typeSel){ typeSel.addEventListener('change', applyProfile); }
applyProfile();
</script>
@endpush
