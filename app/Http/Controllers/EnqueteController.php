<?php

namespace App\Http\Controllers;

use App\Enquete\Questionnaire;
use App\Models\ReponseEnquete;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * L'enquête publique sur les besoins des entreprises haïtiennes.
 *
 * Le formulaire existe en français et en créole. La langue choisie change tout
 * l'écran — intitulés, options, messages d'erreur — mais jamais ce qui est
 * enregistré : les réponses à choix sont des clés communes aux deux langues,
 * sans quoi un dépouillement compterait « Restaurant » et « Restoran » comme
 * deux activités différentes.
 */
class EnqueteController extends Controller
{
    public function show(Request $request)
    {
        $langue = $this->langue($request);

        return view('enquete.index', $this->contexte($langue));
    }

    public function store(Request $request)
    {
        $langue = $this->langue($request);

        $valide = $request->validate($this->regles(), $this->messages($langue));

        $reponse = ReponseEnquete::create([
            'reference' => ReponseEnquete::genererReference(),
            'langue' => $langue,

            'entreprise' => $valide['entreprise'] ?? null,
            'type_activite' => $valide['type_activite'],
            // La précision ne concerne que « Autre » : gardée ailleurs, elle
            // contredirait le type choisi.
            'type_activite_autre' => $valide['type_activite'] === 'autre'
                ? ($valide['type_activite_autre'] ?? null)
                : null,
            'ville' => $valide['ville'] ?? null,
            'departement' => $valide['departement'] ?? null,
            'effectif' => $valide['effectif'] ?? null,
            'anciennete' => $valide['anciennete'] ?? null,

            'outils' => $valide['outils'] ?? null,
            'encaissements' => $valide['encaissements'] ?? null,
            'connexion' => $valide['connexion'] ?? null,
            'presences' => $valide['presences'] ?? null,

            'difficultes' => $valide['difficultes'],
            'perte_de_temps' => $valide['perte_de_temps'] ?? null,
            'pertes' => $valide['pertes'] ?? null,
            'priorite' => $valide['priorite'] ?? null,

            'solutions' => $valide['solutions'] ?? null,
            'budget' => $valide['budget'] ?? null,

            'souhaite_contact' => $request->boolean('souhaite_contact'),
            // Les coordonnées ne sont conservées que si la personne a demandé
            // à être recontactée. Les garder après un « non » reviendrait à
            // constituer un fichier qu'elle a refusé.
            'nom_contact' => $request->boolean('souhaite_contact') ? ($valide['nom_contact'] ?? null) : null,
            'whatsapp' => $request->boolean('souhaite_contact') ? ($valide['whatsapp'] ?? null) : null,
            'email' => $request->boolean('souhaite_contact') ? ($valide['email'] ?? null) : null,

            'ip' => $request->ip(),
        ]);

        // La confirmation passe par la session, jamais par un identifiant dans
        // l'URL : elle porte le nom d'une entreprise et ses difficultés, et une
        // URL numérotée se parcourt de 1 à N.
        return redirect()
            ->route('enquete.merci', ['lang' => $langue])
            ->with('enquete_id', $reponse->id);
    }

    public function merci(Request $request)
    {
        $langue = $this->langue($request);
        $id = session('enquete_id');
        $reponse = $id ? ReponseEnquete::find($id) : null;

        if (! $reponse) {
            return redirect()->route('enquete.show', ['lang' => $langue]);
        }

        // Rejouée telle quelle au rafraîchissement, sans repasser par l'envoi.
        $request->session()->reflash();

        return view('enquete.merci', [
            'reponse' => $reponse,
            'langue' => $langue,
            'textes' => Questionnaire::textes($langue),
        ]);
    }

    // ── Outils ───────────────────────────────────────────

    /**
     * La langue vient de l'adresse, et elle y reste : chaque lien de la page la
     * reconduit. Un formulaire qui repasserait en français au premier
     * rechargement ferait recommencer quelqu'un qui ne lit pas le français.
     */
    private function langue(Request $request): string
    {
        return Questionnaire::langueValide(
            $request->input('lang', $request->query('lang'))
        );
    }

    private function contexte(string $langue): array
    {
        return [
            'langue' => $langue,
            'textes' => Questionnaire::textes($langue),
            'langues' => Questionnaire::langues(),
            'types' => Questionnaire::typesActivite(),
            'departements' => Questionnaire::departements(),
            'effectifs' => Questionnaire::effectifs(),
            'anciennetes' => Questionnaire::anciennetes(),
            'outils' => Questionnaire::outils(),
            'encaissements' => Questionnaire::encaissements(),
            'connexions' => Questionnaire::connexions(),
            'presences' => Questionnaire::presences(),
            'difficultes' => Questionnaire::difficultes(),
            'solutions' => Questionnaire::solutions(),
            'budgets' => Questionnaire::budgets(),
        ];
    }

    private function regles(): array
    {
        $cles = fn (array $liste) => Rule::in(array_keys($liste));

        return [
            'lang' => ['nullable', Rule::in(array_keys(Questionnaire::langues()))],

            'entreprise' => 'nullable|string|max:190',
            'type_activite' => ['required', $cles(Questionnaire::typesActivite())],
            // Choisir « Autre » sans préciser laisserait une réponse qu'aucun
            // dépouillement ne peut classer.
            'type_activite_autre' => [
                Rule::requiredIf(fn () => request('type_activite') === 'autre'),
                'nullable', 'string', 'max:150',
            ],
            'ville' => 'nullable|string|max:120',
            'departement' => ['nullable', $cles(Questionnaire::departements())],
            'effectif' => ['nullable', $cles(Questionnaire::effectifs())],
            'anciennete' => ['nullable', $cles(Questionnaire::anciennetes())],

            'outils' => 'nullable|array',
            'outils.*' => [$cles(Questionnaire::outils())],
            'encaissements' => 'nullable|array',
            'encaissements.*' => [$cles(Questionnaire::encaissements())],
            'connexion' => ['nullable', $cles(Questionnaire::connexions())],
            'presences' => 'nullable|array',
            'presences.*' => [$cles(Questionnaire::presences())],

            // La seule question à choix obligatoire : une enquête sur les
            // difficultés dont la réponse n'en nomme aucune n'apprend rien.
            'difficultes' => 'required|array|min:1',
            'difficultes.*' => [$cles(Questionnaire::difficultes())],
            'perte_de_temps' => 'nullable|string|max:2000',
            'pertes' => 'nullable|string|max:2000',
            'priorite' => 'nullable|string|max:2000',

            'solutions' => 'nullable|array',
            'solutions.*' => [$cles(Questionnaire::solutions())],
            'budget' => ['nullable', $cles(Questionnaire::budgets())],

            'souhaite_contact' => 'nullable|boolean',
            // Demander à être rappelé sans laisser de numéro rend la demande
            // intenable : l'équipe verrait une attente qu'elle ne peut honorer.
            'nom_contact' => [
                Rule::requiredIf(fn () => request()->boolean('souhaite_contact')),
                'nullable', 'string', 'max:150',
            ],
            'whatsapp' => [
                Rule::requiredIf(fn () => request()->boolean('souhaite_contact')),
                'nullable', 'string', 'max:40',
            ],
            'email' => 'nullable|email:filter|max:190',
        ];
    }

    private function messages(string $langue): array
    {
        if ($langue === 'ht') {
            return [
                'type_activite.required' => 'Chwazi kalite aktivite w ap fè a.',
                'type_activite.in' => 'Chwazi yon kalite aktivite nan lis la.',
                'type_activite_autre.required' => 'Di nou ki aktivite w ap fè.',
                'difficultes.required' => 'Chwazi omwen yon difikilte — se kesyon ki pi enpòtan an.',
                'difficultes.min' => 'Chwazi omwen yon difikilte — se kesyon ki pi enpòtan an.',
                'nom_contact.required' => 'Ban nou non w pou nou ka kontakte w.',
                'whatsapp.required' => 'Ban nou nimewo WhatsApp ou pou nou ka kontakte w.',
                'email.email' => 'Adrès imèl sa a pa sanble bon.',
            ];
        }

        return [
            'type_activite.required' => 'Choisissez votre type d\'activité.',
            'type_activite.in' => 'Choisissez un type d\'activité dans la liste.',
            'type_activite_autre.required' => 'Précisez votre activité.',
            'difficultes.required' => 'Choisissez au moins une difficulté — c\'est la question la plus importante.',
            'difficultes.min' => 'Choisissez au moins une difficulté — c\'est la question la plus importante.',
            'nom_contact.required' => 'Indiquez votre nom pour que nous puissions vous recontacter.',
            'whatsapp.required' => 'Indiquez votre numéro WhatsApp pour que nous puissions vous recontacter.',
            'email.email' => 'Cette adresse e-mail ne semble pas valide.',
        ];
    }
}
