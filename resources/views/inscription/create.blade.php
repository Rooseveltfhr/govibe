@extends('layouts.app')

@section('title', 'Inscription — GOVIBE Academy')

@section('content')
<!-- Hero Section -->
<section class="gradient-hero py-16 px-4">
    <div class="max-w-4xl mx-auto text-center">
        <div class="inline-flex items-center bg-yellow-400/20 border border-yellow-400/30 text-yellow-300 text-sm px-4 py-1.5 rounded-full mb-6">
            <i class="fas fa-star mr-2 text-xs"></i>
            Formations Professionnelles en Haïti
        </div>
        <h1 class="text-4xl md:text-5xl font-extrabold text-white mb-4 leading-tight">
            Rejoignez <span class="text-yellow-400">GOVIBE Academy</span>
        </h1>
        <p class="text-blue-200 text-lg max-w-2xl mx-auto">
            Développez vos compétences et transformez votre avenir grâce à nos formations de qualité. Inscrivez-vous dès aujourd'hui.
        </p>
    </div>
</section>

<!-- Form Section -->
<section class="py-12 px-4 -mt-6">
    <div class="max-w-3xl mx-auto">
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden fade-in-up">
            <!-- Form header -->
            <div class="bg-gradient-to-r from-navy to-blue-700 px-8 py-6" style="background: linear-gradient(135deg, #1e3a5f, #2d5a8e)">
                <h2 class="text-2xl font-bold text-white">Formulaire d'Inscription</h2>
                <p class="text-blue-200 text-sm mt-1">Remplissez tous les champs marqués d'un <span class="text-yellow-400">*</span></p>
            </div>

            @if($errors->any())
                <div class="mx-8 mt-6 bg-red-50 border border-red-200 rounded-xl p-4">
                    <div class="flex items-start space-x-3">
                        <i class="fas fa-exclamation-triangle text-red-500 mt-0.5"></i>
                        <div>
                            <p class="font-semibold text-red-800 text-sm">Veuillez corriger les erreurs suivantes :</p>
                            <ul class="mt-2 space-y-1">
                                @foreach($errors->all() as $error)
                                    <li class="text-red-600 text-sm">• {{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <form action="{{ route('inscription.store') }}" method="POST" class="p-8 space-y-8">
                @csrf

                <!-- Section 1: Informations personnelles -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <span class="w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold mr-3 text-white" style="background:#1e3a5f">1</span>
                        Informations Personnelles
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <!-- Nom complet -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nom complet <span class="text-red-500">*</span></label>
                            <input type="text" name="nom_complet" value="{{ old('nom_complet') }}"
                                   placeholder="Ex: Jean-Pierre LOUIS"
                                   class="form-input w-full border border-gray-300 rounded-xl px-4 py-3 text-gray-800 focus:outline-none focus:border-yellow-500 @error('nom_complet') border-red-400 bg-red-50 @enderror">
                        </div>

                        <!-- Sexe -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Sexe <span class="text-red-500">*</span></label>
                            <select name="sexe" class="form-input w-full border border-gray-300 rounded-xl px-4 py-3 text-gray-800 focus:outline-none focus:border-yellow-500 @error('sexe') border-red-400 bg-red-50 @enderror">
                                <option value="">-- Sélectionner --</option>
                                <option value="Masculin" {{ old('sexe') == 'Masculin' ? 'selected' : '' }}>Masculin</option>
                                <option value="Féminin" {{ old('sexe') == 'Féminin' ? 'selected' : '' }}>Féminin</option>
                            </select>
                        </div>

                        <!-- Date de naissance -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Date de naissance <span class="text-red-500">*</span></label>
                            <input type="date" name="date_naissance" value="{{ old('date_naissance') }}"
                                   class="form-input w-full border border-gray-300 rounded-xl px-4 py-3 text-gray-800 focus:outline-none focus:border-yellow-500 @error('date_naissance') border-red-400 bg-red-50 @enderror">
                        </div>

                        <!-- Téléphone -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Téléphone / WhatsApp <span class="text-red-500">*</span></label>
                            <input type="tel" name="telephone" value="{{ old('telephone') }}"
                                   placeholder="+509 XXXX XXXX"
                                   class="form-input w-full border border-gray-300 rounded-xl px-4 py-3 text-gray-800 focus:outline-none focus:border-yellow-500 @error('telephone') border-red-400 bg-red-50 @enderror">
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Adresse e-mail <span class="text-red-500">*</span></label>
                            <input type="email" name="email" value="{{ old('email') }}"
                                   placeholder="exemple@email.com"
                                   class="form-input w-full border border-gray-300 rounded-xl px-4 py-3 text-gray-800 focus:outline-none focus:border-yellow-500 @error('email') border-red-400 bg-red-50 @enderror">
                        </div>
                    </div>
                </div>

                <!-- Section 2: Localisation -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <span class="w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold mr-3 text-white" style="background:#1e3a5f">2</span>
                        Localisation
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <!-- Département -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Département <span class="text-red-500">*</span></label>
                            <select name="departement" id="departement" class="form-input w-full border border-gray-300 rounded-xl px-4 py-3 text-gray-800 focus:outline-none focus:border-yellow-500 @error('departement') border-red-400 bg-red-50 @enderror">
                                <option value="">-- Sélectionner --</option>
                                @foreach(['Artibonite', 'Centre', 'Grand\'Anse', 'Nippes', 'Nord', 'Nord-Est', 'Nord-Ouest', 'Ouest', 'Sud', 'Sud-Est'] as $dept)
                                    <option value="{{ $dept }}" {{ old('departement') == $dept ? 'selected' : '' }}>{{ $dept }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Ville -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Ville <span class="text-red-500">*</span></label>
                            <select name="ville" id="ville" class="form-input w-full border border-gray-300 rounded-xl px-4 py-3 text-gray-800 focus:outline-none focus:border-yellow-500 @error('ville') border-red-400 bg-red-50 @enderror">
                                <option value="">-- Sélectionner d'abord un département --</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Profil -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <span class="w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold mr-3 text-white" style="background:#1e3a5f">3</span>
                        Profil Académique & Professionnel
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <!-- Profession -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Profession ou occupation</label>
                            <input type="text" name="profession" value="{{ old('profession') }}"
                                   placeholder="Ex: Étudiant, Enseignant, Entrepreneur..."
                                   class="form-input w-full border border-gray-300 rounded-xl px-4 py-3 text-gray-800 focus:outline-none focus:border-yellow-500">
                        </div>

                        <!-- Niveau d'étude -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Niveau d'étude <span class="text-red-500">*</span></label>
                            <select name="niveau_etude" class="form-input w-full border border-gray-300 rounded-xl px-4 py-3 text-gray-800 focus:outline-none focus:border-yellow-500 @error('niveau_etude') border-red-400 bg-red-50 @enderror">
                                <option value="">-- Sélectionner --</option>
                                @foreach(['Primaire', 'Secondaire (1ère à 3ème)', 'Secondaire (4ème à Philo)', 'BAC', 'Licence (en cours)', 'Licence (obtenue)', 'Master', 'Doctorat', 'Formation professionnelle', 'Autodidacte'] as $niveau)
                                    <option value="{{ $niveau }}" {{ old('niveau_etude') == $niveau ? 'selected' : '' }}>{{ $niveau }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Formation -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <span class="w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold mr-3 text-white" style="background:#1e3a5f">4</span>
                        Formation & Motivation
                    </h3>
                    <div class="space-y-5">
                        <!-- Formation choisie -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Formation choisie <span class="text-red-500">*</span></label>
                            <select name="formation_id" class="form-input w-full border border-gray-300 rounded-xl px-4 py-3 text-gray-800 focus:outline-none focus:border-yellow-500 @error('formation_id') border-red-400 bg-red-50 @enderror">
                                <option value="">-- Sélectionner une formation --</option>
                                @foreach($formations as $formation)
                                    <option value="{{ $formation->id }}" {{ old('formation_id') == $formation->id ? 'selected' : '' }}>
                                        {{ $formation->nom }}
                                        @if($formation->date_debut) — {{ $formation->date_debut->format('d/m/Y') }} @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Source d'information -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Comment avez-vous connu cette formation ? <span class="text-red-500">*</span></label>
                            <select name="source_info" class="form-input w-full border border-gray-300 rounded-xl px-4 py-3 text-gray-800 focus:outline-none focus:border-yellow-500 @error('source_info') border-red-400 bg-red-50 @enderror">
                                <option value="">-- Sélectionner --</option>
                                @foreach(['Facebook', 'Instagram', 'WhatsApp', 'Twitter / X', 'LinkedIn', 'Bouche à oreille / Ami(e)', 'Affiche / Flyer', 'Radio / Télévision', 'Site web de GOVIBE', 'Autre'] as $source)
                                    <option value="{{ $source }}" {{ old('source_info') == $source ? 'selected' : '' }}>{{ $source }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Objectif -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Ce que vous recherchez après la formation</label>
                            <textarea name="objectif" rows="3" placeholder="Décrivez vos objectifs professionnels après cette formation..."
                                      class="form-input w-full border border-gray-300 rounded-xl px-4 py-3 text-gray-800 focus:outline-none focus:border-yellow-500 resize-none">{{ old('objectif') }}</textarea>
                        </div>

                        <!-- Attentes -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Vos attentes de cette formation</label>
                            <textarea name="attentes" rows="3" placeholder="Qu'attendez-vous de cette formation ? Quelles compétences souhaitez-vous acquérir ?"
                                      class="form-input w-full border border-gray-300 rounded-xl px-4 py-3 text-gray-800 focus:outline-none focus:border-yellow-500 resize-none">{{ old('attentes') }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="pt-4 border-t border-gray-100">
                    <button type="submit" class="btn-primary w-full text-gray-900 font-bold py-4 px-8 rounded-xl text-lg flex items-center justify-center space-x-3">
                        <i class="fas fa-paper-plane"></i>
                        <span>S'inscrire maintenant</span>
                    </button>
                    <p class="text-center text-gray-400 text-xs mt-3">
                        <i class="fas fa-lock mr-1"></i>
                        Vos données sont sécurisées et confidentielles
                    </p>
                </div>
            </form>
        </div>

        <!-- Features -->
        <div class="grid grid-cols-3 gap-4 mt-8">
            @foreach([['fas fa-certificate', 'Certification', 'Attestation officielle'], ['fas fa-chalkboard-teacher', 'Expert', 'Formateurs qualifiés'], ['fas fa-users', 'Réseau', 'Communauté active']] as [$icon, $title, $desc])
            <div class="bg-white rounded-xl p-4 text-center shadow-sm border border-gray-100 card-hover">
                <i class="{{ $icon }} text-2xl mb-2" style="color:#d4a017"></i>
                <p class="font-semibold text-gray-800 text-sm">{{ $title }}</p>
                <p class="text-gray-500 text-xs">{{ $desc }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
const villes = {
    'Artibonite': ['Gonaïves', 'Saint-Marc', 'Dessalines', 'Marchand-Dessalines', 'Gros-Morne', 'Ennery', 'Verrettes', 'Desdunes', 'La Chapelle', 'L\'Estère'],
    'Centre': ['Hinche', 'Mirebalais', 'Lascahobas', 'Belladère', 'Thomonde', 'Boucan-Carré', 'Saut-d\'Eau'],
    'Grand\'Anse': ['Jérémie', 'Abricots', 'Anse-d\'Hainault', 'Beaumont', 'Corail', 'Dame-Marie', 'Moron', 'Roseaux', 'Tiburon'],
    'Nippes': ['Miragoâne', 'Anse-à-Veau', 'Arnaud', 'Baradères', 'Fond-des-Nègres', 'L\'Asile', 'Paillant', 'Petite-Rivière-de-Nippes', 'Plaisance-du-Sud'],
    'Nord': ['Cap-Haïtien', 'Acul-du-Nord', 'Bahon', 'Borgne', 'Grande-Rivière-du-Nord', 'Limbé', 'Milot', 'Plaisance', 'Ranquitte', 'Saint-Raphaël'],
    'Nord-Est': ['Fort-Liberté', 'Carice', 'Capotille', 'Ferrier', 'Mont-Organisé', 'Ouanaminthe', 'Sainte-Suzanne', 'Trou-du-Nord'],
    'Nord-Ouest': ['Port-de-Paix', 'Anse-à-Foleur', 'Baie-de-Henne', 'Bombardopolis', 'Chansolme', 'Jean-Rabel', 'Môle-Saint-Nicolas', 'Saint-Louis-du-Nord'],
    'Ouest': ['Port-au-Prince', 'Carrefour', 'Croix-des-Bouquets', 'Delmas', 'Ganthier', 'Kenscoff', 'Pétion-Ville', 'Tabarre', 'Cité Soleil', 'Gressier', 'Léogâne', 'Arcahaie'],
    'Sud': ['Les Cayes', 'Aquin', 'Arniquet', 'Camp-Perrin', 'Cavaillon', 'Chantal', 'Île-à-Vache', 'Port-Salut', 'Saint-Jean-du-Sud', 'Tiburon', 'Torbeck'],
    'Sud-Est': ['Jacmel', 'Bainet', 'Belle-Anse', 'Cayes-Jacmel', 'Grand-Gosier', 'La Vallée', 'Marigot', 'Thiotte'],
};

const departementSelect = document.getElementById('departement');
const villeSelect = document.getElementById('ville');
const oldVille = @json(old('ville', ''));

departementSelect.addEventListener('change', function () {
    const dept = this.value;
    villeSelect.innerHTML = '<option value="">-- Sélectionner une ville --</option>';
    if (dept && villes[dept]) {
        villes[dept].forEach(v => {
            const opt = document.createElement('option');
            opt.value = v;
            opt.textContent = v;
            if (v === oldVille) opt.selected = true;
            villeSelect.appendChild(opt);
        });
    }
});

// Restore previous selection on page reload
if (departementSelect.value) {
    departementSelect.dispatchEvent(new Event('change'));
}
</script>
@endpush
