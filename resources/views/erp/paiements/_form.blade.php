{{--
  Champs partagés par l'ajout et la modification d'un moyen de paiement.
  $passerelle vaut null à l'ajout.
  Le préfixe d'id évite les collisions : plusieurs formulaires coexistent sur
  la page (l'ajout, plus un par moyen en cours de modification).
--}}
@php $p = $passerelle ? 'mp' . $passerelle->id . '_' : 'new_'; @endphp

<div class="grid gap-4" style="grid-template-columns: repeat(2,1fr);">
    <div>
        <label for="{{ $p }}nom" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">
            Nom affiché <span class="text-red-500">*</span>
        </label>
        <input type="text" id="{{ $p }}nom" name="nom" required
               value="{{ old('nom', $passerelle->nom ?? '') }}"
               placeholder="MonCash"
               class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm dark:bg-slate-700 dark:text-gray-200 focus:outline-none focus:border-red-400">
    </div>
    <div>
        <label for="{{ $p }}type" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">
            Type <span class="text-red-500">*</span>
        </label>
        <select id="{{ $p }}type" name="type" required
                class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm dark:bg-slate-700 dark:text-gray-200 focus:outline-none focus:border-red-400">
            @foreach (\App\Models\PasserellePaiement::types() as $v => $l)
                <option value="{{ $v }}" @selected(old('type', $passerelle->type ?? 'mobile_money') === $v)>{{ $l }}</option>
            @endforeach
        </select>
        <p class="text-xs text-gray-400 mt-1">« Lien de paiement » attend une URL, les autres un numéro.</p>
    </div>
</div>

<div class="grid gap-4 mt-4" style="grid-template-columns: repeat(2,1fr);">
    <div>
        <label for="{{ $p }}titulaire" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">
            Au nom de
        </label>
        <input type="text" id="{{ $p }}titulaire" name="titulaire"
               value="{{ old('titulaire', $passerelle->titulaire ?? '') }}"
               placeholder="Nom du titulaire du compte"
               class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm dark:bg-slate-700 dark:text-gray-200 focus:outline-none focus:border-red-400">
        <p class="text-xs text-gray-400 mt-1">Le client s'en sert pour vérifier à qui il envoie l'argent.</p>
    </div>
    <div>
        <label for="{{ $p }}numero" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">
            Numéro de compte ou adresse
        </label>
        <input type="text" id="{{ $p }}numero" name="numero_compte"
               value="{{ old('numero_compte', $passerelle->numero_compte ?? '') }}"
               placeholder="34420793"
               class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm dark:bg-slate-700 dark:text-gray-200 focus:outline-none focus:border-red-400 font-mono">
    </div>
</div>

<div class="grid gap-4 mt-4" style="grid-template-columns: repeat(2,1fr);">
    <div>
        <label for="{{ $p }}reseau" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">
            Réseau (crypto)
        </label>
        <input type="text" id="{{ $p }}reseau" name="reseau"
               value="{{ old('reseau', $passerelle->reseau ?? '') }}"
               placeholder="TRC20 — Tron"
               class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm dark:bg-slate-700 dark:text-gray-200 focus:outline-none focus:border-red-400">
        <p class="text-xs text-gray-400 mt-1">Laisser vide hors cryptomonnaie.</p>
    </div>
    <div>
        <label for="{{ $p }}lien" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">
            Lien de paiement
        </label>
        <input type="url" id="{{ $p }}lien" name="lien_paiement"
               value="{{ old('lien_paiement', $passerelle->lien_paiement ?? '') }}"
               placeholder="https://www.paypal.com/ncp/payment/..."
               class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm dark:bg-slate-700 dark:text-gray-200 focus:outline-none focus:border-red-400">
    </div>
</div>

<div class="mt-4">
    <label for="{{ $p }}instructions" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">
        Instructions pour le client
    </label>
    <textarea id="{{ $p }}instructions" name="instructions" rows="2"
              placeholder="Ce que le client doit faire après avoir payé, avertissements sur le réseau..."
              class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm dark:bg-slate-700 dark:text-gray-200 focus:outline-none focus:border-red-400 resize-y">{{ old('instructions', $passerelle->instructions ?? '') }}</textarea>
</div>

<div class="grid gap-4 mt-4" style="grid-template-columns: repeat(2,1fr);">
    <div>
        <label for="{{ $p }}qr" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">
            QR code (JPG, PNG, WEBP — max 2 Mo)
        </label>
        @if ($passerelle?->qr_code_url)
            <div class="flex items-center gap-2 mb-2">
                <img src="{{ $passerelle->qr_code_url }}" alt=""
                     class="w-12 h-12 object-contain rounded border border-gray-200 dark:border-slate-600 bg-white">
                <span class="text-xs text-gray-400">QR actuel. Choisir un fichier le remplace.</span>
            </div>
        @endif
        <input type="file" id="{{ $p }}qr" name="qr_code" accept="image/jpeg,image/png,image/webp"
               class="w-full text-xs text-gray-600 dark:text-gray-300 file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-xs file:bg-red-50 file:text-red-600 hover:file:bg-red-100">
    </div>
    <div>
        <label for="{{ $p }}logo" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">
            Logo (JPG, PNG, WEBP — max 1 Mo)
        </label>
        @if ($passerelle?->logo_url)
            <div class="flex items-center gap-2 mb-2">
                <img src="{{ $passerelle->logo_url }}" alt=""
                     class="w-12 h-12 object-contain rounded border border-gray-200 dark:border-slate-600 bg-white">
                <span class="text-xs text-gray-400">Logo actuel.</span>
            </div>
        @endif
        <input type="file" id="{{ $p }}logo" name="logo" accept="image/jpeg,image/png,image/webp"
               class="w-full text-xs text-gray-600 dark:text-gray-300 file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-xs file:bg-red-50 file:text-red-600 hover:file:bg-red-100">
        <p class="text-xs text-gray-400 mt-1">Sans logo, les initiales du nom sont affichées.</p>
    </div>
</div>

<div class="grid gap-4 mt-4 items-end" style="grid-template-columns: repeat(2,1fr);">
    <div>
        <label for="{{ $p }}ordre" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">
            Ordre d'affichage
        </label>
        <input type="number" id="{{ $p }}ordre" name="ordre" min="0" max="9999"
               value="{{ old('ordre', $passerelle->ordre ?? 0) }}"
               class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm dark:bg-slate-700 dark:text-gray-200 focus:outline-none focus:border-red-400">
    </div>
    <div class="pb-2">
        {{-- hidden avant la case : sans lui, décocher n'enverrait rien --}}
        <input type="hidden" name="actif" value="0">
        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300 cursor-pointer">
            <input type="checkbox" name="actif" value="1"
                   {{ old('actif', $passerelle->actif ?? true) ? 'checked' : '' }}
                   class="rounded border-gray-300 text-red-600 focus:ring-red-400">
            Proposer sur le site
        </label>
    </div>
</div>
