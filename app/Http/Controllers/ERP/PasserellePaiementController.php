<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Models\PasserellePaiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PasserellePaiementController extends Controller
{
    public function index()
    {
        $passerelles = PasserellePaiement::orderBy('ordre')->orderBy('nom')->get();

        $stats = [
            'total'       => $passerelles->count(),
            'actives'     => $passerelles->where('actif', true)->count(),
            'incompletes' => $passerelles->where('est_incomplete', true)->count(),
            'sans_qr'     => $passerelles->whereNull('qr_code')->count(),
        ];

        return view('erp.paiements.index', compact('passerelles', 'stats'));
    }

    public function store(Request $request)
    {
        $data = $this->valider($request);

        $data['code'] = $this->genererCode($data['nom']);
        $data = $this->gererFichiers($request, $data, null);

        PasserellePaiement::create($data);

        return back()->with('success', 'Moyen de paiement ajouté.');
    }

    public function update(Request $request, PasserellePaiement $passerelle)
    {
        $data = $this->valider($request, $passerelle);
        $data = $this->gererFichiers($request, $data, $passerelle);

        $passerelle->update($data);

        return back()->with('success', 'Moyen de paiement mis à jour.');
    }

    public function destroy(PasserellePaiement $passerelle)
    {
        foreach ([$passerelle->qr_code, $passerelle->logo] as $fichier) {
            if ($fichier && Storage::disk('public')->exists($fichier)) {
                Storage::disk('public')->delete($fichier);
            }
        }

        $passerelle->delete();

        return back()->with('success', 'Moyen de paiement supprimé.');
    }

    /**
     * Retire un fichier sans supprimer la passerelle.
     */
    public function destroyFichier(Request $request, PasserellePaiement $passerelle)
    {
        $champ = $request->validate([
            'champ' => ['required', Rule::in(['qr_code', 'logo'])],
        ])['champ'];

        if ($passerelle->$champ && Storage::disk('public')->exists($passerelle->$champ)) {
            Storage::disk('public')->delete($passerelle->$champ);
        }

        $passerelle->update([$champ => null]);

        return back()->with('success', $champ === 'logo' ? 'Logo supprimé.' : 'QR code supprimé.');
    }

    private function valider(Request $request, ?PasserellePaiement $passerelle = null): array
    {
        $data = $request->validate([
            'nom'  => 'required|string|max:120',
            'type' => ['required', Rule::in(array_keys(PasserellePaiement::types()))],
            'titulaire'     => 'nullable|string|max:150',
            'numero_compte' => 'nullable|string|max:255',
            'reseau'        => 'nullable|string|max:100',
            'lien_paiement' => 'nullable|url|max:500',
            'instructions'  => 'nullable|string|max:2000',
            // mimes en plus de « image » : écarte le SVG, qui peut porter du
            // script exécuté par le navigateur des visiteurs.
            'qr_code' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
            'logo'    => 'nullable|image|mimes:jpeg,jpg,png,webp|max:1024',
            'actif'   => 'nullable|boolean',
            'ordre'   => 'nullable|integer|min:0|max:9999',
        ], [
            'nom.required'       => 'Le nom est obligatoire.',
            'lien_paiement.url'  => 'Le lien doit être une URL complète (https://…).',
            'qr_code.image'      => 'Le QR code doit être une image (JPG, PNG ou WEBP).',
            'logo.image'         => 'Le logo doit être une image (JPG, PNG ou WEBP).',
            'qr_code.max'        => 'Le QR code ne doit pas dépasser 2 Mo.',
            'logo.max'           => 'Le logo ne doit pas dépasser 1 Mo.',
        ]);

        // Une case décochée n'est pas envoyée : sans valeur explicite,
        // désactiver un moyen de paiement serait impossible.
        return array_merge($data, [
            'actif' => $request->boolean('actif'),
            'ordre' => $request->integer('ordre'),
        ]);
    }

    /**
     * QR code et logo. Sans nouveau fichier, la valeur existante est laissée
     * intacte : « enregistrer » ne doit pas effacer une image déjà en place.
     */
    private function gererFichiers(Request $request, array $data, ?PasserellePaiement $passerelle): array
    {
        foreach (['qr_code', 'logo'] as $champ) {
            if (! $request->hasFile($champ)) {
                unset($data[$champ]);
                continue;
            }

            // L'ancien fichier n'est supprimé que s'il vivait sur le disque
            // public : les images livrées avec le dépôt ne sont pas à nous.
            $ancien = $passerelle?->$champ;
            if ($ancien && Storage::disk('public')->exists($ancien)) {
                Storage::disk('public')->delete($ancien);
            }

            $data[$champ] = $request->file($champ)->store('paiements', 'public');
        }

        return $data;
    }

    private function genererCode(string $nom): string
    {
        $base = Str::slug($nom, '_') ?: 'passerelle';
        $code = $base;
        $n = 2;

        while (PasserellePaiement::where('code', $code)->exists()) {
            $code = $base . '_' . $n++;
        }

        return $code;
    }
}
