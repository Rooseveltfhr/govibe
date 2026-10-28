<?php

namespace App\Http\Controllers\ERP;

use App\Enquete\Questionnaire;
use App\Http\Controllers\Controller;
use App\Models\ReponseEnquete;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EnqueteAdminController extends Controller
{
    /** Les libellés de l'ERP sont en français, la langue de travail de l'équipe. */
    private const LANGUE = 'fr';

    public function index(Request $request)
    {
        $requete = ReponseEnquete::latest();

        if ($statut = $request->query('statut')) {
            $requete->where('statut', $statut);
        }
        if ($type = $request->query('type')) {
            $requete->where('type_activite', $type);
        }
        if ($request->query('rappel') === 'oui') {
            $requete->where('souhaite_contact', true);
        }
        if ($q = trim((string) $request->query('q'))) {
            $requete->where(function ($w) use ($q) {
                $w->where('reference', 'like', "%{$q}%")
                    ->orWhere('entreprise', 'like', "%{$q}%")
                    ->orWhere('nom_contact', 'like', "%{$q}%")
                    ->orWhere('whatsapp', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('ville', 'like', "%{$q}%");
            });
        }

        // Le filtre de type sert aussi au dépouillement : « quelles difficultés
        // reviennent chez les restaurants » est la question à laquelle une
        // enquête doit répondre, pas seulement « voici 300 fiches ».
        $type = $request->query('type') ?: null;

        return view('erp.enquete.index', [
            'reponses' => $requete->paginate(25)->withQueryString(),
            'stats' => [
                'total' => ReponseEnquete::count(),
                'nouvelles' => ReponseEnquete::nouvelles()->count(),
                'a_rappeler' => ReponseEnquete::aRappeler()->count(),
                'creole' => ReponseEnquete::where('langue', 'ht')->count(),
            ],
            'difficultes' => $this->palmares('difficultes', Questionnaire::difficultes(), $type),
            'solutions' => $this->palmares('solutions', Questionnaire::solutions(), $type),
            'parType' => $this->parType(),
            'types' => Questionnaire::typesActivite(),
            'statuts' => Questionnaire::statuts(),
            'langue' => self::LANGUE,
        ]);
    }

    public function show(ReponseEnquete $reponse)
    {
        $reponse->load('agent');

        // Ouvrir une fiche la marque lue : sans cela, le compteur des nouvelles
        // ne descendrait jamais et cesserait d'indiquer quoi que ce soit.
        if ($reponse->statut === 'nouvelle') {
            $reponse->forceFill(['statut' => 'lue'])->save();
        }

        return view('erp.enquete.show', [
            'reponse' => $reponse,
            'statuts' => Questionnaire::statuts(),
            'langue' => self::LANGUE,
        ]);
    }

    public function update(Request $request, ReponseEnquete $reponse)
    {
        $valide = $request->validate([
            'statut' => ['required', Rule::in(array_keys(Questionnaire::statuts()))],
            'notes_internes' => 'nullable|string|max:2000',
        ]);

        $reponse->update([
            'statut' => $valide['statut'],
            'notes_internes' => $valide['notes_internes'] ?? $reponse->notes_internes,
            'traitee_par' => $request->user()->id,
            'traitee_le' => now(),
        ]);

        return back()->with('success', "Réponse {$reponse->reference} mise à jour.");
    }

    public function destroy(ReponseEnquete $reponse)
    {
        $reference = $reponse->reference;
        $reponse->delete();

        return redirect()->route('erp.enquete.index')
            ->with('success', "Réponse {$reference} supprimée.");
    }

    public function export()
    {
        $nom = 'enquete-besoins-'.now()->format('Ymd-Hi').'.csv';

        return response()->streamDownload(function () {
            $sortie = fopen('php://output', 'w');
            // BOM UTF-8 : sans lui Excel casse les accents.
            fwrite($sortie, "\xEF\xBB\xBF");

            fputcsv($sortie, [
                'Référence', 'Reçue le', 'Langue',
                'Entreprise', 'Activité', 'Ville', 'Département', 'Effectif', 'Ancienneté',
                'Outils actuels', 'Encaissement', 'Internet', 'Présence en ligne',
                'Difficultés', 'Perte de temps', 'Pertes argent/clients', 'Priorité',
                'Solutions intéressantes', 'Budget',
                'Veut être rappelé', 'Nom', 'WhatsApp', 'E-mail', 'Statut',
            ], ';', '"', '');

            ReponseEnquete::latest()->chunk(200, function ($lot) use ($sortie) {
                foreach ($lot as $r) {
                    fputcsv($sortie, [
                        $r->reference,
                        $r->created_at->format('d/m/Y H:i'),
                        Questionnaire::langues()[$r->langue] ?? $r->langue,
                        $r->entreprise,
                        $r->typeLibelle(self::LANGUE),
                        $r->ville,
                        $r->departementLibelle(self::LANGUE),
                        $r->effectifLibelle(self::LANGUE),
                        $r->ancienneteLibelle(self::LANGUE),
                        implode(' · ', $r->outilsLibelles(self::LANGUE)),
                        implode(' · ', $r->encaissementsLibelles(self::LANGUE)),
                        $r->connexionLibelle(self::LANGUE),
                        implode(' · ', $r->presencesLibelles(self::LANGUE)),
                        implode(' · ', $r->difficultesLibelles(self::LANGUE)),
                        $r->perte_de_temps,
                        $r->pertes,
                        $r->priorite,
                        implode(' · ', $r->solutionsLibelles(self::LANGUE)),
                        $r->budgetLibelle(self::LANGUE),
                        $r->souhaite_contact ? 'Oui' : 'Non',
                        $r->nom_contact,
                        $r->whatsapp,
                        $r->email,
                        $r->statutLibelle(self::LANGUE),
                    ], ';', '"', '');
                }
            });

            fclose($sortie);
        }, $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ── Dépouillement ────────────────────────────────────

    /**
     * Les réponses les plus fréquentes d'une question à choix multiples, avec
     * leur part. C'est la lecture qui fait d'une pile de fiches une enquête.
     *
     * @return array<int, array{libelle: string, total: int, part: int}>
     */
    private function palmares(string $colonne, array $liste, ?string $type): array
    {
        $frequences = ReponseEnquete::frequences($colonne, $type);
        $repondants = ReponseEnquete::query()
            ->when($type, fn ($q) => $q->where('type_activite', $type))
            ->count();

        if ($repondants === 0) {
            return [];
        }

        $palmares = [];

        foreach (array_slice($frequences, 0, 8, true) as $cle => $total) {
            $palmares[] = [
                'libelle' => Questionnaire::libelle($liste, $cle, self::LANGUE) ?? $cle,
                'total' => $total,
                // Part des répondants, pas des réponses : une personne peut
                // cocher cinq difficultés, et « 40 % des répondants » se lit,
                // là où « 12 % des cases cochées » ne veut rien dire.
                'part' => (int) round($total / $repondants * 100),
            ];
        }

        return $palmares;
    }

    /** @return array<int, array{cle: string, libelle: string, total: int}> */
    private function parType(): array
    {
        $comptes = ReponseEnquete::query()
            ->selectRaw('type_activite, COUNT(*) as total')
            ->groupBy('type_activite')
            ->pluck('total', 'type_activite')
            ->all();

        arsort($comptes);

        return array_map(fn ($cle, $total) => [
            'cle' => $cle,
            'libelle' => Questionnaire::libelle(Questionnaire::typesActivite(), $cle, self::LANGUE) ?? $cle,
            'total' => $total,
        ], array_keys($comptes), $comptes);
    }
}
