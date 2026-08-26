{{--
  Champs partagés par la création et la modification d'un événement.
  $evenement vaut null à la création.
  Le préfixe d'id évite les collisions quand plusieurs formulaires coexistent
  sur la page (création + une édition par événement).
--}}
@php $p = $evenement ? 'ev' . $evenement->id . '_' : 'new_'; @endphp

<div class="grid gap-4" style="grid-template-columns: repeat(2,1fr);">
    <div>
        <label for="{{ $p }}titre" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">
            Titre <span class="text-red-500">*</span>
        </label>
        <input type="text" id="{{ $p }}titre" name="titre" required
               value="{{ old('titre', $evenement->titre ?? '') }}"
               placeholder="FEMINOI"
               class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm dark:bg-slate-700 dark:text-gray-200 focus:outline-none focus:border-red-400">
    </div>
    <div>
        <label for="{{ $p }}sous_titre" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Sous-titre</label>
        <input type="text" id="{{ $p }}sous_titre" name="sous_titre"
               value="{{ old('sous_titre', $evenement->sous_titre ?? '') }}"
               placeholder="Forum sur l'employabilité et les opportunités des infirmières"
               class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm dark:bg-slate-700 dark:text-gray-200 focus:outline-none focus:border-red-400">
    </div>
</div>

<div class="mt-4">
    <label for="{{ $p }}description" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Description</label>
    <textarea id="{{ $p }}description" name="description" rows="3"
              placeholder="Présentation de l'événement affichée au-dessus du formulaire."
              class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm dark:bg-slate-700 dark:text-gray-200 focus:outline-none focus:border-red-400 resize-y">{{ old('description', $evenement->description ?? '') }}</textarea>
</div>

<div class="grid gap-4 mt-4" style="grid-template-columns: repeat(3,1fr);">
    <div>
        <label for="{{ $p }}lieu" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Lieu</label>
        <input type="text" id="{{ $p }}lieu" name="lieu"
               value="{{ old('lieu', $evenement->lieu ?? '') }}"
               placeholder="Port-au-Prince, Haïti"
               class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm dark:bg-slate-700 dark:text-gray-200 focus:outline-none focus:border-red-400">
    </div>
    <div>
        <label for="{{ $p }}date_debut" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Date de début</label>
        <input type="date" id="{{ $p }}date_debut" name="date_debut"
               value="{{ old('date_debut', $evenement?->date_debut?->format('Y-m-d') ?? '') }}"
               class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm dark:bg-slate-700 dark:text-gray-200 focus:outline-none focus:border-red-400">
    </div>
    <div>
        <label for="{{ $p }}date_fin" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Date de fin</label>
        <input type="date" id="{{ $p }}date_fin" name="date_fin"
               value="{{ old('date_fin', $evenement?->date_fin?->format('Y-m-d') ?? '') }}"
               class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm dark:bg-slate-700 dark:text-gray-200 focus:outline-none focus:border-red-400">
    </div>
</div>

<div class="mt-4">
    <label for="{{ $p }}whatsapp" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">
        Lien du groupe WhatsApp
    </label>
    <input type="url" id="{{ $p }}whatsapp" name="whatsapp_group_url"
           value="{{ old('whatsapp_group_url', $evenement->whatsapp_group_url ?? '') }}"
           placeholder="https://chat.whatsapp.com/..."
           class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm dark:bg-slate-700 dark:text-gray-200 focus:outline-none focus:border-red-400">
    <p class="text-xs text-gray-400 mt-1">Affiché sur le bouton « Rejoindre le groupe », après l'inscription et sur la page de l'événement.</p>
</div>

<div class="grid gap-4 mt-4 items-end" style="grid-template-columns: repeat(3,1fr);">
    <div>
        <label for="{{ $p }}ordre" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Ordre d'affichage</label>
        <input type="number" id="{{ $p }}ordre" name="ordre" min="0" max="9999"
               value="{{ old('ordre', $evenement->ordre ?? 0) }}"
               class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-3 py-2 text-sm dark:bg-slate-700 dark:text-gray-200 focus:outline-none focus:border-red-400">
    </div>
    <div class="pb-2">
        {{-- hidden avant la case : sans lui, décocher n'enverrait rien --}}
        <input type="hidden" name="actif" value="0">
        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300 cursor-pointer">
            <input type="checkbox" name="actif" value="1"
                   {{ old('actif', $evenement->actif ?? true) ? 'checked' : '' }}
                   class="rounded border-gray-300 text-red-600 focus:ring-red-400">
            Visible dans la liste
        </label>
    </div>
    <div class="pb-2">
        <input type="hidden" name="inscriptions_ouvertes" value="0">
        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300 cursor-pointer">
            <input type="checkbox" name="inscriptions_ouvertes" value="1"
                   {{ old('inscriptions_ouvertes', $evenement->inscriptions_ouvertes ?? true) ? 'checked' : '' }}
                   class="rounded border-gray-300 text-red-600 focus:ring-red-400">
            Inscriptions ouvertes
        </label>
    </div>
</div>
